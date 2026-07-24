<?php

namespace App\Integrations\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 外部付款账号接口客户端。
 *
 * Controller 不直接拼外部接口，也不相信前端传来的账号名称。
 * 所有付款账号实时查询都集中在这里，方便后续按公司真实协议调整。
 */
class PaymentAccountClient
{
    /**
     * 获取所有付款账号。
     *
     * 优先读取缓存；缓存不存在时才访问外部接口，并在成功后写入缓存。
     *
     * @return list<PaymentAccount>
     */
    public function fetchAll(): array
    {
        if ($accounts = $this->cachedAccounts()) {
            return $accounts;
        }

        // 发布任务弹窗需要展示所有可选付款账号，所以这里按列表接口读取外部主数据。
        $accounts = $this->fetchAllFromRemote();
        $this->putCacheIfNotEmpty($accounts);

        return $accounts;
    }

    /**
     * 刷新付款账号缓存。
     *
     * 这个方法供定时任务调用；如果外部接口返回空列表或失败，不覆盖旧缓存。
     */
    public function refreshCache(): int
    {
        // 定时任务调用该方法刷新 Redis。
        // 如果外部接口失败或返回空列表，不覆盖旧缓存，避免页面突然没有可选账号。
        $accounts = $this->fetchAllFromRemote();

        if ($accounts === []) {
            return 0;
        }

        $this->putCacheIfNotEmpty($accounts);

        return count($accounts);
    }

    /**
     * 从外部接口实时获取所有付款账号。
     *
     * 该方法只负责远程请求，不读取缓存，适合缓存刷新场景使用。
     *
     * @return list<PaymentAccount>
     */
    private function fetchAllFromRemote(): array
    {
        $payload = $this->requestConfiguredPath(
            pathConfigKey: 'payment_account.list_path',
            emptyPathMessage: 'Payment account list path is not configured.',
        );

        return PaymentAccount::listFromPayload($payload);
    }

    /**
     * 根据账号 ID 获取单个付款账号。
     *
     * 保存任务时使用该方法重新向后端确认账号信息，避免相信前端提交的账号名称。
     */
    public function fetchById(string $accountId): PaymentAccount
    {
        foreach ($this->cachedAccounts() as $paymentAccount) {
            if ($paymentAccount->accountId() === $accountId) {
                return $paymentAccount;
            }
        }

        $detailPath = config('payment_account.detail_path');

        if (is_string($detailPath) && $detailPath !== '') {
            // 如果外部系统提供单账号详情接口，就按账号 ID 查询，减少不必要的列表数据传输。
            return PaymentAccount::fromPayload($accountId, $this->requestConfiguredPath(
                pathConfigKey: 'payment_account.detail_path',
                emptyPathMessage: 'Payment account detail path is not configured.',
                accountId: $accountId,
            ));
        }

        // 如果当前只有“全部账号列表”接口，就从列表中查找用户选择的账号。
        // 这样保存时仍然不相信前端提交的账号名称，只保存外部接口返回的那条账号快照。
        foreach ($this->fetchAll() as $paymentAccount) {
            if ($paymentAccount->accountId() === $accountId) {
                return $paymentAccount;
            }
        }

        throw new PaymentAccountException('Selected payment account does not exist.');
    }

    /**
     * 按配置发起付款账号外部接口请求。
     *
     * 统一处理 base_url、path、HTTP 方法、SSL 校验、超时和 JSON 解析。
     */
    private function requestConfiguredPath(
        string $pathConfigKey,
        string $emptyPathMessage,
        ?string $accountId = null,
    ): array {
        $baseUrl = config('payment_account.base_url');
        $path = config($pathConfigKey);

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new PaymentAccountException('Payment account base URL is not configured.');
        }

        if (! is_string($path) || $path === '') {
            throw new PaymentAccountException($emptyPathMessage);
        }

        if ($this->isAbsoluteUrl($path)) {
            throw new PaymentAccountException('Payment account path must be a path, not a full URL.');
        }

        $method = strtoupper((string) config('payment_account.method', 'GET'));

        try {
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('payment_account.timeout', 3))
                ->acceptJson();

            if (! config('payment_account.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链；生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = match ($method) {
                'POST' => $request->asJson()->post($path, $this->payloadForAccount($accountId)),
                'GET' => $request->get($this->pathWithAccountId($path, $accountId), $this->queryForPath($path, $accountId)),
                default => throw new PaymentAccountException('Unsupported payment account HTTP method.'),
            };
        } catch (ConnectionException $exception) {
            throw new PaymentAccountException('Unable to connect to payment account service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new PaymentAccountException(sprintf(
                'Payment account request failed with HTTP status %d.',
                $response->status(),
            ));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new PaymentAccountException('Payment account response is not a JSON object.');
        }

        return $payload;
    }

    /**
     * 从缓存读取付款账号列表。
     *
     * 如果 Redis 或缓存服务不可用，方法会记录 warning 并返回空数组，让调用方退回外部接口。
     *
     * @return list<PaymentAccount>
     */
    private function cachedAccounts(): array
    {
        try {
            $cached = Cache::store((string) config('payment_account.cache_store', 'redis'))
                ->get((string) config('payment_account.cache_key', 'taskhub:payment_accounts'));
        } catch (Throwable $exception) {
            Log::warning('Unable to read payment account cache.', [
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! is_array($cached)) {
            return [];
        }

        return PaymentAccount::listFromPayload($cached);
    }

    /**
     * 将非空付款账号列表写入缓存。
     *
     * 空列表不写入缓存，避免外部接口短暂异常时覆盖掉上一次成功同步的数据。
     *
     * @param  list<PaymentAccount>  $accounts
     */
    private function putCacheIfNotEmpty(array $accounts): void
    {
        if ($accounts === []) {
            return;
        }

        try {
            Cache::store((string) config('payment_account.cache_store', 'redis'))
                ->put(
                    (string) config('payment_account.cache_key', 'taskhub:payment_accounts'),
                    array_map(fn (PaymentAccount $account): array => $account->toCachePayload(), $accounts),
                    (int) config('payment_account.cache_ttl', 86400),
                );
        } catch (Throwable $exception) {
            Log::warning('Unable to write payment account cache.', [
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 将账号 ID 填充到配置路径中。
     *
     * 支持 /accounts/{accountId} 这种 REST 风格路径。
     */
    private function pathWithAccountId(string $path, ?string $accountId): string
    {
        // 支持 /accounts/{accountId} 形式；如果 path 没有占位符，则用 query string 传 accountId。
        return $accountId === null ? $path : str_replace('{accountId}', rawurlencode($accountId), $path);
    }

    /**
     * 为 GET 详情接口生成查询参数。
     *
     * 如果路径中没有 {accountId} 占位符，就把 accountId 放到 query string。
     *
     * @return array<string, string>
     */
    private function queryForPath(string $path, ?string $accountId): array
    {
        if ($accountId === null || str_contains($path, '{accountId}')) {
            return [];
        }

        return ['accountId' => $accountId];
    }

    /**
     * 为 POST 详情接口生成 JSON body。
     *
     * 列表接口没有账号 ID，详情接口才需要把账号 ID 放入请求体。
     *
     * @return array<string, string>
     */
    private function payloadForAccount(?string $accountId): array
    {
        // 列表接口通常不需要 body；详情接口才把账号 ID 放到 JSON body 中。
        return $accountId === null ? [] : ['accountId' => $accountId];
    }

    /**
     * 判断配置值是否是完整 URL。
     *
     * TaskHub 要求 path 配置只写路径，完整域名统一放在 base_url。
     */
    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

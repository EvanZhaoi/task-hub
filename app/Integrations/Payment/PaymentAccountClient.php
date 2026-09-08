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
    public function fetchAll(?string $accessToken = null): array
    {
        if ($accounts = $this->cachedAccounts()) {
            return $accounts;
        }

        // 发布任务弹窗需要展示所有可选付款账号，所以这里按列表接口读取外部主数据。
        $accounts = $this->fetchAllFromRemote($accessToken);
        $this->putCacheIfNotEmpty($accounts);

        return $accounts;
    }

    /**
     * 刷新付款账号缓存。
     *
     * 这个方法供定时任务调用；如果外部接口返回空列表或失败，不覆盖旧缓存。
     */
    public function refreshCache(?string $accessToken = null): int
    {
        // 该方法可以由已经拿到动态 SSO accessToken 的调用方刷新 Redis。
        // 注意：accessToken 是登录后从 Session 中取得的动态值，不从 .env 固定配置读取。
        // 如果外部接口失败或返回空列表，不覆盖旧缓存，避免页面突然没有可选账号。
        $accounts = $this->fetchAllFromRemote($accessToken);

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
    private function fetchAllFromRemote(?string $accessToken): array
    {
        $payload = $this->requestConfiguredPath(
            pathConfigKey: 'payment_account.list_path',
            emptyPathMessage: 'Payment account list path is not configured.',
            accessToken: $accessToken,
        );

        return PaymentAccount::listFromPayload($payload);
    }

    /**
     * 根据账号 ID 从全量列表中查找付款账号。
     *
     * 当前外部系统没有“按账号 ID 查询单个账号”的接口。
     * 因此这里不会再调用单条详情接口，而是从全量账号列表缓存或列表接口结果中筛选。
     */
    public function fetchById(string $accountId, ?string $accessToken = null): PaymentAccount
    {
        foreach ($this->fetchAll($accessToken) as $paymentAccount) {
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
        ?string $accessToken,
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
        $token = $this->normalizeAccessToken($accessToken);

        try {
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('payment_account.timeout', 3))
                ->acceptJson()
                // 付款账号接口已切换为 token 调用方式，所有真实请求都必须带 Authorization Header。
                ->withHeaders(['Authorization' => 'bearer '.$token]);

            if (! config('payment_account.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链；生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = match ($method) {
                'POST' => $request->asJson()->post($path),
                'GET' => $request->get($path),
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
     * 判断配置值是否是完整 URL。
     *
     * TaskHub 要求 path 配置只写路径，完整域名统一放在 base_url。
     */
    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * 校验外部接口 token 是否可用。
     *
     * accessToken 必须由当前登录 Session 或调用方显式传入。
     * 不在这里读取 .env 固定 token，避免把个人登录凭证写进配置文件。
     */
    private function normalizeAccessToken(?string $accessToken): string
    {
        if (is_string($accessToken) && $accessToken !== '') {
            return $accessToken;
        }

        throw new PaymentAccountException('Payment account access token is not configured.');
    }
}

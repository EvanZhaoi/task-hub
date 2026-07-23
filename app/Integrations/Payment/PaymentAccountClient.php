<?php

namespace App\Integrations\Payment;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * 外部付款账号接口客户端。
 *
 * Controller 不直接拼外部接口，也不相信前端传来的账号名称。
 * 所有付款账号实时查询都集中在这里，方便后续按公司真实协议调整。
 */
class PaymentAccountClient
{
    /**
     * @return list<PaymentAccount>
     */
    public function fetchAll(): array
    {
        // 发布任务弹窗需要展示所有可选付款账号，所以这里按列表接口读取外部主数据。
        $payload = $this->requestConfiguredPath(
            pathConfigKey: 'payment_account.list_path',
            emptyPathMessage: 'Payment account list path is not configured.',
        );

        return PaymentAccount::listFromPayload($payload);
    }

    public function fetchById(string $accountId): PaymentAccount
    {
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

    private function pathWithAccountId(string $path, ?string $accountId): string
    {
        // 支持 /accounts/{accountId} 形式；如果 path 没有占位符，则用 query string 传 accountId。
        return $accountId === null ? $path : str_replace('{accountId}', rawurlencode($accountId), $path);
    }

    /**
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
     * @return array<string, string>
     */
    private function payloadForAccount(?string $accountId): array
    {
        // 列表接口通常不需要 body；详情接口才把账号 ID 放到 JSON body 中。
        return $accountId === null ? [] : ['accountId' => $accountId];
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

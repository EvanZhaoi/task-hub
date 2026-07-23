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
    public function fetchById(string $accountId): PaymentAccount
    {
        $baseUrl = config('payment_account.base_url');
        $path = config('payment_account.path');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new PaymentAccountException('Payment account base URL is not configured.');
        }

        if (! is_string($path) || $path === '') {
            throw new PaymentAccountException('Payment account path is not configured.');
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
                'POST' => $request->asJson()->post($path, ['accountId' => $accountId]),
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

        return PaymentAccount::fromPayload($accountId, $payload);
    }

    private function pathWithAccountId(string $path, string $accountId): string
    {
        // 支持 /accounts/{accountId} 形式；如果 path 没有占位符，则用 query string 传 accountId。
        return str_replace('{accountId}', rawurlencode($accountId), $path);
    }

    /**
     * @return array<string, string>
     */
    private function queryForPath(string $path, string $accountId): array
    {
        return str_contains($path, '{accountId}') ? [] : ['accountId' => $accountId];
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

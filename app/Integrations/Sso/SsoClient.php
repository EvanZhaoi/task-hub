<?php

namespace App\Integrations\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SsoClient
{
    public function fetchCurrentUser(string $accessToken): SsoUser
    {
        // SSO 基础地址和接口路径属于公司协议配置，不在代码中写死，便于不同环境切换。
        $baseUrl = config('sso.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoException('SSO base URL is not configured.');
        }

        // userinfo_path 是当前推荐命名；validate_path 保留为早期配置兼容入口。
        $userInfoPath = config('sso.userinfo_path') ?: config('sso.validate_path');

        if (! is_string($userInfoPath) || $userInfoPath === '') {
            throw new SsoException('SSO user info path is not configured.');
        }

        if ($this->isAbsoluteUrl($userInfoPath)) {
            throw new SsoException('SSO user info path must be a path, not a full URL.');
        }

        try {
            // TODO: 按公司 SSO 文档确认真实请求方法、路径、Header 和返回结构。
            // 当前骨架表达的是关键安全边界：后端用 accessToken 换取当前登录人信息。
            $response = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 5))
                ->acceptJson()
                ->withToken($accessToken)
                ->get($userInfoPath);
        } catch (ConnectionException $exception) {
            throw new SsoException('Unable to connect to SSO user info service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new SsoException(sprintf('SSO user info request failed with HTTP status %d.', $response->status()));
        }

        $payload = $response->json();

        // 当前登录人接口必须返回 JSON 对象，后续由 SsoUser 统一解析 employeeNo 等字段。
        if (! is_array($payload)) {
            throw new SsoException('SSO user info response is not a JSON object.');
        }

        return SsoUser::fromPayload($payload);
    }

    public function validateToken(string $token): SsoUser
    {
        // 预留给 Bearer Token API 场景；当前 Inertia 页面登录主要使用 fetchCurrentUser。
        return $this->fetchCurrentUser($token);
    }

    private function isAbsoluteUrl(string $value): bool
    {
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }
}

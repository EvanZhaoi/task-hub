<?php

namespace App\Integrations\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * 公司 SSO 接口客户端。
 *
 * 这里集中封装所有真实 SSO HTTP 调用，Controller 不直接拼接口、不直接读公司响应结构。
 * 这样以后总部协议调整时，主要修改本类和 SsoUser 解析逻辑。
 */
class SsoClient
{
    /**
     * 使用授权码 code 换取 access_token。
     *
     * 授权码模式下，浏览器回调到 TaskHub 时只携带 code。
     * client_secret 必须只在 Laravel 后端使用，不能交给 React 或浏览器。
     */
    public function exchangeCodeForToken(string $code, string $redirectUri): SsoToken
    {
        $baseUrl = $this->configuredBaseUrl();
        $clientId = $this->configuredClientId();
        $clientSecret = $this->configuredClientSecret();
        $tokenPath = config('sso.token_path');

        if (! is_string($tokenPath) || $tokenPath === '') {
            throw new SsoException('SSO token path is not configured.');
        }

        if ($this->isAbsoluteUrl($tokenPath)) {
            throw new SsoException('SSO token path must be a path, not a full URL.');
        }

        try {
            // 图片中的 token 接口要求 content-type: application/x-www-form-urlencoded。
            // Laravel 的 asForm() 会把数组编码为表单请求体，而不是 JSON。
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 3))
                ->acceptJson()
                ->asForm();

            if (! config('sso.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链，所以提供配置开关。
                // 生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = $request->post($tokenPath, [
                // code 是公司 SSO 回调到 /sso/callback 时带回来的授权码。
                'code' => $code,
                // client_id/client_secret 由公司 SSO 分配，必须来自后端配置。
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                // redirect_uri 必须和发起授权请求时传给 SSO 的回调地址完全一致。
                'redirect_uri' => $redirectUri,
                // 授权码模式固定使用 authorization_code。
                'grant_type' => 'authorization_code',
            ]);
        } catch (ConnectionException $exception) {
            throw new SsoException('Unable to connect to SSO token service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new SsoException(sprintf('SSO token request failed with HTTP status %d.', $response->status()));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new SsoException('SSO token response is not a JSON object.');
        }

        return SsoToken::fromPayload($payload);
    }

    /**
     * 使用 accessToken 调用总部当前登录人接口。
     *
     * 授权码模式下 accessToken 由 Laravel 后端用 code 换取。
     * clientSecret 只在后端请求中使用，不能暴露给 React 或浏览器。
     */
    public function fetchCurrentUser(string $accessToken): SsoUser
    {
        // SSO 基础地址和接口路径属于公司协议配置，不在代码中写死，便于不同环境切换。
        $baseUrl = $this->configuredBaseUrl();
        $clientId = $this->configuredClientId();
        $clientSecret = $this->configuredClientSecret();

        // userinfo_path 是当前推荐命名；validate_path 保留为早期配置兼容入口。
        $userInfoPath = config('sso.userinfo_path') ?: config('sso.validate_path');

        if (! is_string($userInfoPath) || $userInfoPath === '') {
            throw new SsoException('SSO user info path is not configured.');
        }

        if ($this->isAbsoluteUrl($userInfoPath)) {
            throw new SsoException('SSO user info path must be a path, not a full URL.');
        }

        try {
            // 按公司推荐方式：POST JSON，body 中提交 clientId、secret 和 accessToken。
            $request = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 3))
                ->acceptJson()
                ->asJson();

            if (! config('sso.verify_ssl')) {
                // 内网测试环境可能暂时没有完整证书链，所以提供配置开关。
                // 生产环境应开启 SSL 校验。
                $request = $request->withoutVerifying();
            }

            $response = $request->post($userInfoPath, [
                'clientId' => $clientId,
                'secret' => $clientSecret,
                'accessToken' => $accessToken,
            ]);
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

    /**
     * 校验 Bearer Token 并返回当前登录人。
     *
     * 当前 Inertia 页面主要走 fetchCurrentUser；保留该方法是为了未来 API 场景复用。
     */
    public function validateToken(string $token): SsoUser
    {
        // 预留给 Bearer Token API 场景；当前 Inertia 页面登录主要使用 fetchCurrentUser。
        return $this->fetchCurrentUser($token);
    }

    /**
     * 判断配置值是否是完整 URL。
     *
     * SSO path 配置只允许写路径，避免和 base_url 组合出错误请求地址。
     */
    private function isAbsoluteUrl(string $value): bool
    {
        // userinfo_path 必须是 path，防止 base_url + full_url 拼出错误请求地址。
        return str_starts_with($value, 'http://') || str_starts_with($value, 'https://');
    }

    /**
     * 读取并校验 SSO 基础地址。
     *
     * base_url 只放域名和公共前缀，具体接口路径由 token_path/userinfo_path 单独配置。
     */
    private function configuredBaseUrl(): string
    {
        $baseUrl = config('sso.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoException('SSO base URL is not configured.');
        }

        return $baseUrl;
    }

    /**
     * 读取并校验 SSO client_id。
     *
     * client_id 是公司分配给 TaskHub 的客户端标识。
     */
    private function configuredClientId(): string
    {
        $clientId = config('sso.client_id');

        if (! is_string($clientId) || $clientId === '') {
            throw new SsoException('SSO client ID is not configured.');
        }

        return $clientId;
    }

    /**
     * 读取并校验 SSO client_secret。
     *
     * client_secret 只允许后端使用，不能写入前端代码或传给浏览器。
     */
    private function configuredClientSecret(): string
    {
        $clientSecret = config('sso.client_secret');

        if (! is_string($clientSecret) || $clientSecret === '') {
            throw new SsoException('SSO client secret is not configured.');
        }

        return $clientSecret;
    }
}

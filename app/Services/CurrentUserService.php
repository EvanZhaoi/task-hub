<?php

namespace App\Services;

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use Illuminate\Http\Request;

class CurrentUserService
{
    public const SESSION_KEY = 'sso_user';

    public const ROLE_SESSION_KEY = 'taskhub_roles';

    private ?SsoUser $resolvedUser = null;

    public function __construct(
        private readonly Request $request,
        private readonly SsoClient $ssoClient,
    ) {}

    public function user(): SsoUser
    {
        // 同一次请求内可能多处需要当前用户，解析后缓存到内存中，避免重复读 Session 或调接口。
        if ($this->resolvedUser instanceof SsoUser) {
            return $this->resolvedUser;
        }

        $sessionUser = $this->request->session()->get(self::SESSION_KEY);

        // Inertia 页面登录后，当前用户信息保存在 Laravel Session 中。
        // 后续 Controller、Middleware、Service 都应该通过本服务读取，而不是直接访问 Session。
        if (is_array($sessionUser)) {
            return $this->resolvedUser = SsoUser::fromPayload($sessionUser);
        }

        // 预留给 Bearer Token API 场景。
        // 当前 TaskHub 页面优先走 SSO Callback + Laravel Session，不要求前端每次带 Authorization Header。
        $token = $this->request->bearerToken();

        if (! is_string($token) || $token === '') {
            throw new SsoException('Missing SSO bearer token.');
        }

        return $this->resolvedUser = $this->ssoClient->validateToken($token);
    }

    public function employeeNo(): string
    {
        return $this->user()->employeeNo();
    }

    public function roles(): array
    {
        // TaskHub 业务角色在登录时写入 Session，来源是 taskhub_user_role 表。
        // 这里做一次类型过滤，避免 Session 中出现非字符串值影响权限判断。
        $roles = $this->request->session()->get(self::ROLE_SESSION_KEY, []);

        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}

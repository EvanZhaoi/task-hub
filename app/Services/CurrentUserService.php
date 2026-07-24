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

    /**
     * 注入当前 HTTP 请求和 SSO 客户端。
     *
     * Request 用于读取 Laravel Session 或 Bearer Token；SsoClient 只在 Bearer Token API 兜底场景使用。
     */
    public function __construct(
        private readonly Request $request,
        private readonly SsoClient $ssoClient,
    ) {}

    /**
     * 获取当前登录用户。
     *
     * Inertia 页面优先从 Session 读取；如果没有 Session，则尝试 Bearer Token。
     * 同一次请求内会缓存解析结果，避免重复创建 SsoUser 对象或重复调用外部接口。
     */
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

    /**
     * 获取当前登录人的工号。
     *
     * 业务表中的人员引用字段统一保存工号，因此 Controller 可以直接调用该方法得到当前操作者标识。
     */
    public function employeeNo(): string
    {
        return $this->user()->employeeNo();
    }

    /**
     * 获取当前用户在 TaskHub 中的业务角色。
     *
     * 角色在登录时从 taskhub_user_role 表读取并写入 Session，这里只做类型过滤和数组规整。
     *
     * @return list<string>
     */
    public function roles(): array
    {
        // TaskHub 业务角色在登录时写入 Session，来源是 taskhub_user_role 表。
        // 这里做一次类型过滤，避免 Session 中出现非字符串值影响权限判断。
        $roles = $this->request->session()->get(self::ROLE_SESSION_KEY, []);

        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    }

    /**
     * 判断当前用户是否拥有指定 TaskHub 角色。
     *
     * 前端可以做轻量展示控制，但真正权限判断应在后端使用该服务或后续权限服务完成。
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}

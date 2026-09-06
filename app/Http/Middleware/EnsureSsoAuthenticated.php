<?php

namespace App\Http\Middleware;

use App\Integrations\Sso\SsoToken;
use App\Services\CurrentUserService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SSO 登录检查中间件。
 *
 * 保护需要登录访问的 Inertia 页面。
 * 当前 MVP 使用 Laravel Session 保存 SSO 用户快照，不使用 Laravel 默认 users 表。
 */
class EnsureSsoAuthenticated
{
    /**
     * 检查当前请求是否已经完成 SSO 登录。
     *
     * 有 `sso_user` Session 时继续进入业务页面；
     * 没有登录态时记录用户原本访问的 URL，并跳转到 SSO 登录入口。
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 登录成功后，SsoController 会把 sso_user 和 sso_token 写入 Session。
        // 授权码模式下不能只看 sso_user；如果 access_token 已过期，需要重新走 SSO。
        if (
            $request->session()->has(CurrentUserService::SESSION_KEY)
            && SsoToken::sessionPayloadIsValid($request->session()->get(CurrentUserService::TOKEN_SESSION_KEY))
        ) {
            return $next($request);
        }

        // 登录态缺失或 token 过期时，清理旧 Session 片段，避免后续误用过期用户信息。
        $request->session()->forget([
            CurrentUserService::SESSION_KEY,
            CurrentUserService::ROLE_SESSION_KEY,
            CurrentUserService::TOKEN_SESSION_KEY,
        ]);

        // 未登录时保存完整目标 URL，SSO 登录完成后可以回到原页面。
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('sso.login');
    }
}

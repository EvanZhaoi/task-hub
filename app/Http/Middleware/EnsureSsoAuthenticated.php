<?php

namespace App\Http\Middleware;

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
        // 登录成功后，SsoController 会把 sso_user 写入 Session。
        if ($request->session()->has('sso_user')) {
            return $next($request);
        }

        // 未登录时保存完整目标 URL，SSO 登录完成后可以回到原页面。
        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('sso.login');
    }
}

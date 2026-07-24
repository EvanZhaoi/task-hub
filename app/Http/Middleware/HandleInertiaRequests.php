<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/**
 * Inertia 全局请求中间件。
 *
 * Laravel 每次返回 Inertia 页面时都会经过这里。
 * 这里适合放所有页面都需要的共享 props，例如应用名和当前登录人。
 */
class HandleInertiaRequests extends Middleware
{
    // rootView 对应 resources/views/app.blade.php，是 React/Inertia 的 HTML 容器。
    protected $rootView = 'app';

    /**
     * 定义所有 Inertia 页面共享的 props。
     *
     * 这里集中下发应用名、当前 Session 用户、角色和 flash 消息，
     * 避免每个 Controller 重复给 React 页面传相同的基础数据。
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                // 给所有 React 页面提供统一应用名，避免页面硬编码 TaskHub。
                'name' => config('app.name'),
            ],
            'auth' => [
                // 这里共享的是 Session 中的用户快照，权限判断仍应通过后端服务完成。
                'user' => $request->session()->get('sso_user'),
                // 角色来自 taskhub_user_role 表，登录时写入 Session，前端只用于展示。
                'roles' => $request->session()->get('taskhub_roles', []),
            ],
            'flash' => [
                // 表单提交成功后，Controller 可以通过 redirect()->with('success', ...) 给页面一次性提示。
                'success' => $request->session()->get('success'),
            ],
        ];
    }
}

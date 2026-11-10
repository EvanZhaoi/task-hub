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
                // 头像前缀是公开图片地址；不下发 accessToken 或人员接口原始数据。
                'avatarBaseUrl' => config('personnel.avatar_base_url'),
            ],
            'auth' => [
                // 只共享页面展示需要的字段，邮箱、token 和外部接口原始数据不得发送到浏览器。
                'user' => $this->sharedUser($request),
                // 角色来自 taskhub_user_role 表，登录时写入 Session，前端只用于展示。
                'roles' => $request->session()->get('taskhub_roles', []),
            ],
            'flash' => [
                // 表单提交成功后，Controller 可以通过 redirect()->with('success', ...) 给页面一次性提示。
                'success' => $request->session()->get('success'),
            ],
        ];
    }

    /**
     * 从 SSO Session 中挑选允许发送给 React 的公开展示字段。
     *
     * Session 还可能保存邮箱和外部接口 raw 数据，这些字段只供后端业务使用，不能原样成为 Inertia Props。
     *
     * @return array<string, mixed>|null
     */
    private function sharedUser(Request $request): ?array
    {
        $user = $request->session()->get('sso_user');

        if (! is_array($user)) {
            return null;
        }

        $shared = [
            'employeeNo' => $user['employeeNo'] ?? null,
            'displayName' => $user['displayName'] ?? null,
            'departmentName' => $user['departmentName'] ?? null,
            'avatarId' => $user['avatarId'] ?? null,
        ];

        if (isset($user['siteUser']) && is_array($user['siteUser'])) {
            $shared['siteUser'] = [
                'employeeNo' => $user['siteUser']['employeeNo'] ?? null,
                'displayName' => $user['siteUser']['displayName'] ?? null,
                'departmentId' => $user['siteUser']['departmentId'] ?? null,
                'departmentName' => $user['siteUser']['departmentName'] ?? null,
                'avatarId' => $user['siteUser']['avatarId'] ?? null,
            ];
        }

        return $shared;
    }
}

<?php

namespace App\Http\Controllers;

use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SSO 控制器。
 *
 * 负责浏览器登录跳转、SSO 回调页、本地 Session 建立和退出。
 * 真实人员信息只由后端 SsoClient 调公司接口获取，前端不能决定当前用户是谁。
 */
class SsoController extends Controller
{
    /**
     * 发起 SSO 登录跳转。
     *
     * 当用户访问受保护页面但没有本地 Session 时，会进入该方法。
     * 方法负责拼接公司 SSO 登录地址，并记录登录完成后要返回的原始业务页面。
     */
    public function redirect(Request $request): RedirectResponse
    {
        // 登录地址和客户端标识由公司 SSO 分配，必须来自环境配置，不能写死在代码里。
        $loginUrl = config('sso.login_url');
        $clientId = config('sso.client_id');

        if (! is_string($loginUrl) || $loginUrl === '') {
            abort(503, 'SSO login URL is not configured.');
        }

        if (! is_string($clientId) || $clientId === '') {
            abort(503, 'SSO client ID is not configured.');
        }

        // 记录用户原本想访问的页面，SSO 完成后再跳回去。
        // 如果没有明确的目标页面，则默认进入任务列表。
        $request->session()->put('url.intended', $request->session()->get('url.intended', route('tasks.index')));

        // 公司当前 SSO 使用隐式模式，回调时只返回 access_token。
        // 标准 OAuth/OIDC 常见的 state 校验当前不可用，因此这里不生成、不传递 state。
        $query = http_build_query(array_filter([
            // 隐式模式要求浏览器最终拿到 access token。
            'response_type' => 'token',
            'client_id' => $clientId,
            // Laravel 生成绝对回调地址，避免不同环境手写 callback URL。
            'redirect_uri' => route('sso.callback'),
            'scope' => config('sso.scope'),
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return redirect()->away($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').$query);
    }

    /**
     * 返回 SSO 回调 React 页面。
     *
     * 公司 SSO 登录完成后会携带 access_token 回到该页面。
     * 页面本身不解析用户身份，只负责把 token 提交给后端 `/sso/session`。
     */
    public function callback(): Response
    {
        // 公司 SSO 会把 access_token 作为 query string 回调到这个地址。
        // 这里仍返回 React 回调页，由前端统一处理登录中、失败提示和提交 /sso/session。
        return Inertia::render('Sso/Callback');
    }

    /**
     * 使用 accessToken 建立 TaskHub 本地登录 Session。
     *
     * 该方法先通过 SsoClient 调总部接口确认当前登录人，再尝试从本据点人员列表中追加 siteUser。
     * 前端不能提交姓名、部门或角色，角色必须由后端从 taskhub_user_role 表读取。
     */
    public function store(
        Request $request,
        SsoClient $ssoClient,
        PersonnelClient $personnelClient,
        TaskhubRoleService $roleService,
    ): JsonResponse {
        // 前端只提交 accessToken，不提交姓名、部门或角色。
        // 当前登录人的可信身份必须由后端拿 token 调公司 SSO 接口得到。
        $validated = $request->validate([
            // 字段名使用 accessToken，是为了和公司接口示例保持一致；进入后端后仍按字符串处理。
            'accessToken' => ['required', 'string'],
        ]);

        try {
            // 使用 accessToken 获取当前登录人信息，这是 TaskHub 建立本地会话的身份依据。
            $user = $ssoClient->fetchCurrentUser($validated['accessToken']);
        } catch (SsoException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 401);
        }

        $roles = $roleService->rolesFor($user);
        $sessionUser = $user->toSessionPayload();

        if ($siteUser = $this->siteUserFromPersonnelList($user, $personnelClient)) {
            // 总部 SSO 信息是认证来源，不能被本据点人员信息覆盖。
            // 本据点信息作为额外数组字段保存，供页面展示和未来人员选择器使用。
            $sessionUser['siteUser'] = $siteUser->toSessionPayload();
        }

        // Laravel Session 保存的是已认证用户快照和 TaskHub 本地业务角色。
        // 角色来自 taskhub_user_role 表，变更角色不需要重新发布应用。
        $request->session()->put(CurrentUserService::SESSION_KEY, $sessionUser);
        $request->session()->put(CurrentUserService::ROLE_SESSION_KEY, $roles);

        // 登录成功后刷新 Session ID，降低会话固定攻击风险。
        $request->session()->regenerate();

        return response()->json([
            'redirectTo' => $request->session()->pull('url.intended', route('tasks.index')),
            'roles' => $roles,
        ]);
    }

    /**
     * 根据总部 SSO 用户工号查找本据点人员信息。
     *
     * 找到时返回 PersonnelUser，由 store() 写入 Session 的 siteUser 字段。
     * 找不到或人员接口失败时返回 null，保持总部 SSO 原始人员信息不变。
     */
    private function siteUserFromPersonnelList(SsoUser $ssoUser, PersonnelClient $personnelClient): ?PersonnelUser
    {
        try {
            $personnelUser = $personnelClient->findByEmployeeNo($ssoUser->employeeNo());
        } catch (PersonnelException $exception) {
            // 人员列表用于增强 Session 信息，不应该让总部 SSO 已认证用户因为本地列表接口临时失败而无法登录。
            // 这里记录日志后回退到总部 SSO 返回的信息。
            Log::warning('Unable to enrich SSO user from personnel list.', [
                'employeeNo' => $ssoUser->employeeNo(),
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (! $personnelUser instanceof PersonnelUser) {
            return null;
        }

        return $personnelUser;
    }

    /**
     * 退出 TaskHub 本地会话，并按配置跳转公司 SSO 退出地址。
     *
     * 退出必须使用原生 POST 表单提交，避免 Inertia Ajax 跟随外部 302 跳转时触发浏览器 CORS。
     */
    public function logout(Request $request): RedirectResponse
    {
        // 退出 TaskHub 时清理本系统保存的登录态和角色。
        // 这一步必须先做，避免用户从总部 SSO 返回时 TaskHub 仍保留旧 Session。
        $request->session()->forget([
            CurrentUserService::SESSION_KEY,
            CurrentUserService::ROLE_SESSION_KEY,
        ]);

        // 让整个 Laravel Session 失效，确保旧 Session ID 不能继续使用。
        $request->session()->invalidate();

        // 退出后刷新 CSRF token，避免旧页面继续复用退出前的 token。
        $request->session()->regenerateToken();

        // 如果公司 SSO 提供统一退出地址，则本地退出完成后继续跳转到总部退出页。
        // SSO_LOGOUT_URL 是浏览器跳转地址，所以这里使用完整 URL，并用 away() 避免 Laravel 当作站内路径处理。
        $logoutUrl = config('sso.logout_url');

        if (is_string($logoutUrl) && $logoutUrl !== '') {
            // 退出表单使用原生 POST，不是 Inertia Ajax。
            // 因此外部 302 会作为浏览器顶层导航处理，不会触发跨域 XHR CORS。
            return redirect()->away($logoutUrl);
        }

        return redirect()->route('home');
    }
}

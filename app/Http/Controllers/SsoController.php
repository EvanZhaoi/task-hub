<?php

namespace App\Http\Controllers;

use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoToken;
use App\Integrations\Sso\SsoUser;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SSO 控制器。
 *
 * 负责浏览器登录跳转、授权码回调、本地 Session 建立和退出。
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

        // 公司当前 SSO 使用授权码模式，回调时返回 code。
        // 之后由 Laravel 后端使用 code + client_secret 换 access_token。
        $query = http_build_query(array_filter([
            // 授权码模式固定传 code，不能再使用隐式模式的 token。
            'response_type' => 'code',
            'client_id' => $clientId,
            // Laravel 生成绝对回调地址，避免不同环境手写 callback URL。
            'redirect_uri' => route('sso.callback'),
            'scope' => config('sso.scope'),
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return redirect()->away($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').$query);
    }

    /**
     * 处理公司 SSO 授权码回调。
     *
     * 公司 SSO 登录完成后会携带 code 回到该地址。
     * Laravel 后端使用 code 换 access_token，再用 access_token 查询当前登录人并建立 Session。
     */
    public function callback(
        Request $request,
        SsoClient $ssoClient,
        PersonnelClient $personnelClient,
        TaskhubRoleService $roleService,
    ): Response|RedirectResponse {
        // 授权码模式下，公司 SSO 回调到 TaskHub 时必须携带 code。
        // 缺少 code 时直接显示错误页，避免 Laravel 表单校验重定向让登录问题变得难排查。
        $code = $request->query('code');

        if (! is_string($code) || $code === '') {
            return Inertia::render('Sso/Callback', [
                'status' => 'failed',
                'message' => 'SSO 回调缺少 code。',
            ]);
        }

        try {
            // redirect_uri 必须和 /login 发起授权请求时传递给 SSO 的值保持一致。
            $token = $ssoClient->exchangeCodeForToken($code, route('sso.callback'));
            // 拿到 access_token 后，继续由后端调用总部当前登录人接口。
            $user = $ssoClient->fetchCurrentUser($token->accessToken());
        } catch (SsoException $exception) {
            // 回调失败时返回 React 提示页，不把 code、access_token 或 client_secret 暴露给页面。
            return Inertia::render('Sso/Callback', [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ]);
        }

        $this->establishSession($request, $user, $token, $personnelClient, $roleService);

        return redirect()->intended(route('tasks.index'));
    }

    /**
     * 建立 TaskHub 本地登录 Session。
     *
     * 该方法先保存总部 SSO 当前登录人，再尝试从本据点人员列表中追加 siteUser。
     * 前端不能提交姓名、部门或角色，角色必须由后端从 taskhub_user_role 表读取。
     */
    private function establishSession(
        Request $request,
        SsoUser $user,
        SsoToken $token,
        PersonnelClient $personnelClient,
        TaskhubRoleService $roleService,
    ): void {
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
        $request->session()->put(CurrentUserService::TOKEN_SESSION_KEY, $token->toSessionPayload());

        // 登录成功后刷新 Session ID，降低会话固定攻击风险。
        $request->session()->regenerate();
    }

    /**
     * 根据总部 SSO 用户工号查找本据点人员信息。
     *
     * 找到时返回 PersonnelUser，由 establishSession() 写入 Session 的 siteUser 字段。
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
            CurrentUserService::TOKEN_SESSION_KEY,
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

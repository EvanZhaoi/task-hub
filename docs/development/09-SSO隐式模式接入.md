# 09-SSO隐式模式接入

## 1. 本章目标

本章接入公司 SSO 的隐式模式，并在 TaskHub 内部控制业务角色。

最终链路：

```text
访问业务页面
↓
EnsureSsoAuthenticated
↓
跳转公司 SSO
↓
公司 SSO 回调 /sso/callback#access_token=...
↓
React 回调页读取 URL fragment
↓
POST /sso/session
↓
Laravel 使用 accessToken 调用公司 SSO 当前登录人信息接口
↓
获得 employeeNo / displayName / department 等用户信息
↓
写入 Laravel Session
↓
根据工号解析 TaskHub 本地角色
↓
回到原业务页面
```

## 2. 学习目标

完成本章后，你应该理解：

- 什么是 SSO 隐式模式。
- 为什么 Laravel Controller 不能直接读取 URL `#access_token`。
- 为什么需要一个 React 回调页。
- 为什么 TaskHub 不创建本地 users 表。
- SSO 身份和 TaskHub 业务角色为什么要分开。
- Middleware 如何保护业务页面。
- Laravel Session 在这个流程中的作用。

## 3. 最终效果

访问受保护页面：

```text
http://127.0.0.1:8000/tasks
```

如果还没有登录：

1. Laravel 跳转到公司 SSO 登录页。
2. SSO 认证完成后回调 TaskHub。
3. TaskHub 建立本地 Session。
4. TaskHub 根据工号解析角色。
5. 浏览器回到 `/tasks`。

当前公司接口参数仍需要按真实协议补齐，代码中的调用位置已经保留。

## 4. 涉及文件

新增和修改：

```text
app/Http/Controllers/SsoController.php
app/Http/Middleware/EnsureSsoAuthenticated.php
app/Http/Middleware/HandleInertiaRequests.php
app/Integrations/Sso/SsoClient.php
app/Integrations/Sso/SsoUser.php
app/Services/CurrentUserService.php
app/Services/TaskhubRoleService.php
config/sso.php
config/taskhub.php
routes/web.php
resources/views/app.blade.php
resources/js/Pages/Sso/Callback.tsx
.env.example
```

## 5. 实际执行命令

命令执行目录：项目根目录。

检查路由：

```bash
php artisan route:list
```

检查前端类型：

```bash
npm run typecheck
```

构建前端：

```bash
npm run build
```

运行测试：

```bash
php artisan test
```

## 6. 每一步操作

### 6.1 配置 `.env`

`.env.example` 已增加：

```env
SSO_BASE_URL=
SSO_LOGIN_URL=
SSO_CLIENT_ID=
SSO_SCOPE=
SSO_CALLBACK_PATH=/sso/callback
SSO_USERINFO_PATH=
SSO_VALIDATE_PATH=
SSO_TIMEOUT=5
```

如果你的本地 `.env` 里没有这些字段，不是前面章节删掉了，而是因为：

- `.env` 是你早期从 `.env.example` 复制出来的本地文件。
- 后续新增配置只会进入 `.env.example`。
- Laravel 和 Git 都不会自动把 `.env.example` 的新增字段合并到已经存在的 `.env`。
- `.env` 不提交到仓库，所以每个开发者都需要手动补齐新增配置。

处理方式：打开本地 `.env`，把上面这一整段追加进去，然后按公司 SSO 文档填写真实值。

追加完成后执行：

```bash
php artisan config:clear
php artisan cache:clear
```

原因：

- Laravel 运行时读取的是 `.env`，不是 `.env.example`。
- `.env.example` 只是模板。
- 修改 `.env` 后需要清理配置缓存，确保新值生效。

说明：

- `SSO_LOGIN_URL`：公司 SSO 登录地址。
- `SSO_CLIENT_ID`：公司分配给 TaskHub 的客户端标识。
- `SSO_SCOPE`：公司协议要求的授权范围，没有则留空。
- `SSO_CALLBACK_PATH`：TaskHub 接收回调的路径。
- `SSO_USERINFO_PATH`：TaskHub 后端拿到 access token 后，用它调用公司接口获取当前登录人信息。
- `SSO_VALIDATE_PATH`：兼容旧命名；如果暂时未配置 `SSO_USERINFO_PATH`，代码会回退读取这个路径。

本章不编造公司协议。真实参数需要按公司 SSO 文档填写。

### 6.2 配置 SSO 登录跳转

文件：`app/Http/Controllers/SsoController.php`

关键代码：

```php
public function redirect(Request $request): RedirectResponse
{
    // 生成 state，用于防止回调被伪造。
    $state = Str::random(40);
    $request->session()->put('sso_state', $state);

    // 隐式模式要求 response_type=token。
    $query = http_build_query([
        'response_type' => 'token',
        'client_id' => config('sso.client_id'),
        'redirect_uri' => route('sso.callback'),
        'scope' => config('sso.scope'),
        'state' => $state,
    ]);

    return redirect()->away(config('sso.login_url').'?'.$query);
}
```

作用：

- 把未登录用户送到公司 SSO。
- 使用 `state` 保护登录流程。
- 使用 `redirect()->away()` 跳转到 Laravel 应用外部地址。

### 6.3 理解隐式模式回调

隐式模式常见回调形式：

```text
http://127.0.0.1:8000/sso/callback#access_token=xxx&state=yyy
```

关键点：

- `#access_token=xxx` 属于 URL fragment。
- 浏览器不会把 fragment 发给服务器。
- 所以 Laravel 的 `/sso/callback` Controller 读不到 `access_token`。
- 必须由浏览器中的 JavaScript 读取。

这就是为什么本章新增：

```text
resources/js/Pages/Sso/Callback.tsx
```

### 6.4 React 回调页读取 token

文件：`resources/js/Pages/Sso/Callback.tsx`

先创建目录：

```bash
mkdir -p resources/js/Pages/Sso
```

然后创建完整文件：

```tsx
import { useEffect, useState } from 'react';

type CallbackState = 'processing' | 'failed';

function readAuthParams(): URLSearchParams {
    const hash = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : window.location.hash;

    if (hash !== '') {
        return new URLSearchParams(hash);
    }

    return new URLSearchParams(window.location.search);
}

function csrfToken(): string {
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function SsoCallback() {
    const [state, setState] = useState<CallbackState>('processing');
    const [message, setMessage] = useState('正在完成单点登录，请稍候。');

    useEffect(() => {
        const params = readAuthParams();
        const accessToken = params.get('access_token');
        const ssoState = params.get('state');

        if (!accessToken || !ssoState) {
            setState('failed');
            setMessage('SSO 回调缺少 access_token 或 state。');
            return;
        }

        fetch('/sso/session', {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                accessToken,
                state: ssoState,
            }),
        })
            .then(async (response) => {
                const payload = (await response.json()) as { redirectTo?: string; message?: string };

                if (!response.ok) {
                    throw new Error(payload.message ?? 'SSO 登录失败。');
                }

                window.location.replace(payload.redirectTo ?? '/tasks');
            })
            .catch((error: unknown) => {
                setState('failed');
                setMessage(error instanceof Error ? error.message : 'SSO 登录失败。');
            });
    }, []);

    return (
        <main className="flex min-h-screen items-center justify-center bg-[#fafafa] px-6 text-[#1a1a1a]">
            <section className="w-full max-w-md rounded-lg border border-[#ebebeb] bg-white p-6 shadow-sm">
                <div className="mb-4 flex items-center gap-3">
                    <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                        T
                    </div>
                    <div>
                        <h1 className="m-0 text-lg font-semibold">TaskHub SSO</h1>
                        <p className="mt-1 text-sm text-[#6e6e80]">
                            {state === 'processing' ? '正在建立本地会话' : '登录未完成'}
                        </p>
                    </div>
                </div>

                <p className="text-sm leading-6 text-[#6e6e80]">{message}</p>

                {state === 'failed' ? (
                    <a
                        className="mt-5 inline-flex rounded-md bg-[#5e6ad2] px-4 py-2 text-sm font-medium text-white"
                        href="/login"
                    >
                        重新登录
                    </a>
                ) : null}
            </section>
        </main>
    );
}
```

这个页面的作用不是展示业务内容，而是完成 SSO 隐式模式的浏览器侧收尾工作：

```text
读取 access_token 和 state
↓
POST 给 Laravel
↓
Laravel 建立 Session
↓
跳转回原业务页面
```

#### 6.4.1 为什么要用 `useEffect`

`useEffect(() => { ... }, [])` 表示页面第一次挂载后执行一次。

这里不能在组件函数体里直接 `fetch()`，原因是 React 组件渲染可能执行多次。如果把网络请求直接写在组件函数体里，可能导致重复提交 `/sso/session`。

这和 Java 后端里“不要在 getter 或构造对象时做外部副作用”类似：登录收尾是副作用，应该放在明确的生命周期里。

#### 6.4.2 为什么先读 hash，再读 query string

隐式模式标准上通常返回：

```text
/sso/callback#access_token=xxx&state=yyy
```

也就是 token 在 `#` 后面。

浏览器不会把 `#` 后面的内容发给服务器，所以 Laravel 后端拿不到它。只有浏览器里的 JavaScript 可以通过：

```ts
window.location.hash
```

读取。

代码中仍然保留 query string 兼容：

```ts
return new URLSearchParams(window.location.search);
```

这是为了兼容少数公司 SSO 实现把参数放成：

```text
/sso/callback?access_token=xxx&state=yyy
```

TaskHub 以 hash 为主，query string 只是兼容兜底。

#### 6.4.3 为什么必须提交 `state`

`state` 是登录流程中的防伪随机值。

流程是：

```text
Laravel 生成 state，保存到 Session
↓
跳转 SSO 时带上 state
↓
SSO 回调时原样带回 state
↓
React 把 state 提交给 Laravel
↓
Laravel 比较回调 state 和 Session state
```

如果两边不一致，说明这次回调不可信，后端会返回：

```text
419
SSO state verification failed.
```

#### 6.4.4 POST 给 Laravel 的请求格式

前端请求：

```tsx
fetch('/sso/session', {
    method: 'POST',
    headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': csrfToken(),
    },
    body: JSON.stringify({
        accessToken,
        state: ssoState,
    }),
});
```

后端对应路由：

```php
Route::post('/sso/session', [SsoController::class, 'store'])->name('sso.session.store');
```

后端接收字段：

```php
$validated = $request->validate([
    'accessToken' => ['required', 'string'],
    'state' => ['required', 'string'],
]);
```

字段名必须一致：

```text
React: accessToken
Laravel: accessToken

React: state
Laravel: state
```

#### 6.4.5 为什么要带 CSRF

```tsx
'X-CSRF-TOKEN': csrfToken(),
```

对应 Blade 中新增：

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

Laravel 的 Web 路由默认启用 CSRF 保护。`/sso/session` 是 POST 请求，如果不带 CSRF token，会返回：

```text
419 CSRF token mismatch
```

这里没有关闭 CSRF，也没有把 `/sso/session` 放到 API 路由里，原因是 TaskHub 是 Laravel + Inertia 单体应用，SSO 完成后要建立浏览器 Session，走 Web 路由更合适。

#### 6.4.6 成功后为什么用 `window.location.replace`

```tsx
window.location.replace(payload.redirectTo ?? '/tasks');
```

作用：

- 跳回原本想访问的业务页面。
- 使用 `replace` 而不是 `href = ...`，可以避免用户点击浏览器后退时又回到带 token 的回调地址。

这点很重要：access token 不应该长期留在浏览器历史记录中。

#### 6.4.7 失败时为什么显示重试入口

失败场景包括：

- SSO 没有带回 `access_token`。
- SSO 没有带回 `state`。
- Laravel 校验 state 失败。
- 后端调用公司当前登录人接口失败。
- CSRF 配置不正确。

页面失败时展示“重新登录”，让用户回到 `/login` 重新开始 SSO 流程。

#### 6.4.8 如何验证这一页

先确认路由存在：

```bash
php artisan route:list
```

应该看到：

```text
GET|HEAD  sso/callback
POST      sso/session
```

再确认前端能编译：

```bash
npm run typecheck
npm run build
```

没有公司 SSO 参数时，可以直接访问：

```text
http://127.0.0.1:8000/sso/callback
```

页面应该显示“SSO 回调缺少 access_token 或 state”。这不是错误，而是说明回调页已经加载，只是当前 URL 没有 SSO 带回的参数。

### 6.5 Laravel 用 accessToken 获取当前登录人信息

隐式模式第一步只让 TaskHub 拿到 access token。TaskHub 不能只相信前端传来的用户信息，也不应该让前端自己决定当前用户是谁。

正确流程是：

```text
React 回调页提交 accessToken
↓
Laravel 后端接收 accessToken
↓
Laravel 调用公司 SSO 当前登录人信息接口
↓
公司 SSO 返回 employeeNo / displayName / department
↓
TaskHub 根据 employeeNo 建立 Session 和解析角色
```

文件：`app/Integrations/Sso/SsoClient.php`

关键代码：

```php
public function fetchCurrentUser(string $accessToken): SsoUser
{
    // TODO: 按公司 SSO 文档确认真实请求方法、路径、Header 和返回结构。
    $response = Http::baseUrl(config('sso.base_url'))
        ->timeout((int) config('sso.timeout', 5))
        ->acceptJson()
        ->withToken($accessToken)
        ->get(config('sso.userinfo_path'));

    return SsoUser::fromPayload($response->json());
}
```

这里的核心点：

- access token 只是一把临时凭证。
- 当前登录人工号必须以后端调用公司接口得到的结果为准。
- TaskHub 后续业务角色基于 `employeeNo` 解析。
- 不信任前端传来的姓名、部门或角色。

### 6.6 Laravel 建立 Session

文件：`app/Http/Controllers/SsoController.php`

关键代码：

```php
$user = $ssoClient->fetchCurrentUser($validated['accessToken']);
$roles = $roleService->rolesFor($user);

$request->session()->put(CurrentUserService::SESSION_KEY, $user->toSessionPayload());
$request->session()->put(CurrentUserService::ROLE_SESSION_KEY, $roles);
$request->session()->regenerate();
```

作用：

- 后端用 access token 获取当前登录人信息。
- 获取成功后把用户信息写入 Laravel Session。
- 根据工号解析 TaskHub 本地角色。
- `regenerate()` 用于登录后刷新 Session ID，降低会话固定风险。

## 7. 每一步为什么这么做

### 7.1 为什么不创建本地 users 表

TaskHub 已确定不维护完整本地用户表。

原因：

- 人员身份来自公司 SSO 和外部人员接口。
- 工号是稳定标识。
- 姓名、部门等历史展示信息由业务快照保存。
- 权限判断应基于实时身份和 TaskHub 角色，不依赖本地用户资料表。

### 7.2 为什么 SSO 身份和 TaskHub 角色分开

SSO 只解决“你是谁”：

```text
employeeNo = E10001
displayName = 张三
departmentName = 开发一部
```

TaskHub 角色解决“你在这个系统里能做什么”：

```text
DEVELOPER
PUBLISHER
BOSS
ADMIN
```

这两个概念不要混在一起。

### 7.3 当前角色控制方式

TaskHub 角色不放在 `.env`，而是放在数据库表：

```text
taskhub_user_role
```

原因：

- 角色是业务配置，不是部署配置。
- 改角色不应该要求重新发布或重启应用。
- SSO 只负责认证身份，TaskHub 自己控制业务角色。
- 仍然不创建本地 users 表，只保存工号和角色关系。

表结构核心字段：

```text
employee_no  人员工号
role         DEVELOPER / PUBLISHER / BOSS
enabled      是否启用
```

读取角色的代码在：

```text
app/Services/TaskhubRoleService.php
```

核心逻辑：

```php
return TaskhubUserRole::query()
    ->where('employee_no', $user->employeeNo())
    ->where('enabled', true)
    ->orderBy('role')
    ->pluck('role')
    ->all();
```

角色变更方式：

```sql
INSERT INTO taskhub_user_role (id, employee_no, role)
VALUES (10001, 'E10001', 'PUBLISHER');
```

禁用角色：

```sql
UPDATE taskhub_user_role
SET enabled = 0
WHERE employee_no = 'E10001'
  AND role = 'PUBLISHER';
```

后续如果需要角色管理页面，可以基于这张表做后台维护。本章先只完成数据库控制能力。

## 8. 如何验证

没有公司真实 SSO 参数时，可以验证这些内容：

```bash
php artisan route:list
npm run typecheck
npm run build
php artisan test
```

可以看到路由：

```text
GET|HEAD  login
GET|HEAD  sso/callback
POST      sso/session
POST      logout
GET|HEAD  tasks
```

配置未填写时访问 `/tasks` 会跳转 `/login`，然后因为 `SSO_LOGIN_URL` 或 `SSO_CLIENT_ID` 为空返回配置错误。这是正常的，说明业务页已经被 SSO 保护。

## 9. 常见错误

| 错误 | 常见原因 | 处理 |
|---|---|---|
| 后端拿不到 `access_token` | 隐式模式 token 在 URL fragment 中 | 必须通过 React 回调页读取 hash |
| `SSO login URL is not configured` | `.env` 未配置 `SSO_LOGIN_URL` | 按公司协议填写 |
| `SSO client ID is not configured` | `.env` 未配置 `SSO_CLIENT_ID` | 填写公司分配的 client id |
| `SSO state verification failed` | 回调 state 和 Session 不一致 | 重新登录，检查是否跨域或 Session 配置异常 |
| `419 CSRF token mismatch` | POST `/sso/session` 未带 CSRF | 确认 Blade 有 csrf meta，fetch 有 `X-CSRF-TOKEN` |
| 登录后没有预期角色 | `taskhub_user_role` 中没有启用记录 | 检查该工号是否存在 `enabled = 1` 的角色记录 |

## 10. 本章总结

本章完成：

- SSO 登录跳转。
- 隐式模式回调页。
- access token 提交给 Laravel。
- Laravel 使用 access token 调用公司当前登录人信息接口。
- 当前登录人信息写入 Laravel Session。
- TaskHub 本地角色解析。
- 业务路由 SSO 保护。

核心理解：

```text
SSO 负责认证身份；
TaskHub 负责业务角色；
Session 负责浏览器会话；
Middleware 负责保护业务页面。
```

## 11. 下一章预告

SSO 接入后，可以正式进入业务功能开发。

下一步建议从任务大厅开始：

```text
任务列表查询
↓
状态和复杂度筛选
↓
任务详情只读
↓
发布任务表单
```

前端实现继续沿用原型图风格：浅色背景、顶部导航、紧凑卡片、状态徽章、稳定间距和清晰的页面层级。

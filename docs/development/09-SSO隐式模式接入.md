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

TASKHUB_DEFAULT_ROLE=DEVELOPER
TASKHUB_ADMIN_EMPLOYEE_NOS=
TASKHUB_BOSS_EMPLOYEE_NOS=
TASKHUB_PUBLISHER_EMPLOYEE_NOS=
TASKHUB_DEVELOPER_EMPLOYEE_NOS=
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
- `TASKHUB_*_EMPLOYEE_NOS`：TaskHub 内部角色工号白名单，多个工号用英文逗号分隔。

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

关键代码：

```tsx
function readAuthParams(): URLSearchParams {
    const hash = window.location.hash.startsWith('#') ? window.location.hash.slice(1) : window.location.hash;

    if (hash !== '') {
        return new URLSearchParams(hash);
    }

    return new URLSearchParams(window.location.search);
}
```

作用：

- 优先读取 URL hash。
- 兼容少数 SSO 把参数放在 query string 的情况。
- 读取到 token 后 POST 给 Laravel。

POST 时带上 CSRF：

```tsx
'X-CSRF-TOKEN': csrfToken(),
```

对应 Blade 中新增：

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

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

文件：`config/taskhub.php`

当前使用环境变量中的工号白名单：

```php
'roles' => [
    'ADMIN' => [...],
    'BOSS' => [...],
    'PUBLISHER' => [...],
    'DEVELOPER' => [...],
],
```

优点：

- 不需要本地用户表。
- 实现简单。
- 适合 Phase 1 验证真实 SSO 后快速进入业务开发。

后续如果角色管理复杂，可以再引入独立角色配置后台或外部权限接口。本章不提前设计复杂权限系统。

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
| 登录后没有预期角色 | 工号不在角色白名单 | 检查 `TASKHUB_*_EMPLOYEE_NOS` |

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

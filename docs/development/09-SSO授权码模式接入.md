# 09-SSO授权码模式接入

## 本章目标

本章把 TaskHub 的登录方式接入公司 SSO 授权码模式。

最终流程是：

```text
用户访问 /tasks
↓
Laravel Middleware 发现没有有效 Session 或 access_token 已过期
↓
跳转 /login
↓
Laravel 拼接公司 SSO authorize 地址
↓
浏览器跳转公司 SSO
↓
用户在公司 SSO 页面登录
↓
公司 SSO 回调 /sso/callback?code=xxx
↓
Laravel 后端用 code 换 access_token
↓
Laravel 后端用 access_token 调当前登录人接口
↓
Laravel 查询 TaskHub 本地角色
↓
Laravel 写入 Session
↓
浏览器进入 /tasks
```

本章不开发 TaskHub 业务权限细节，只完成认证闭环和本地角色读取入口。

## 学习目标

完成本章后，你应该理解：

- 授权码模式和隐式模式的区别。
- 为什么 `client_secret` 只能放在 Laravel 后端。
- 为什么 React 页面不应该读取或保存 `access_token`。
- Laravel 如何用 `redirect()->away()` 跳转外部 SSO。
- Laravel 如何在 callback 中用 `code` 换 token。
- Laravel Session 在单体 Inertia 项目中的作用。
- Middleware 为什么要检查 token 是否过期。
- 当前登录人、SSO 用户、TaskHub 角色三者为什么不是一回事。

## 最终效果

访问受保护页面：

```text
http://127.0.0.1:8000/tasks
```

如果未登录：

- Laravel 跳转 `/login`。
- `/login` 继续跳转公司 SSO authorize 地址。
- authorize 请求中包含 `response_type=code`。
- 公司 SSO 登录成功后回调 `/sso/callback?code=...`。
- Laravel 后端调用 token 接口换取 `access_token`。
- Laravel 后端继续用 `access_token` 查询当前登录人。
- Session 中保存：
  - `sso_user`
  - `sso_token`
  - `taskhub_roles`
- 最后进入原本要访问的业务页面。

## 前置条件

你需要已经完成：

- Laravel + React + Inertia 基础项目。
- `resources/views/app.blade.php` 已经包含 CSRF meta。
- `TaskhubUserRole` Model 已经存在。
- `taskhub_user_role` 表已经在 `database/schema.sql` 中定义。

当前约定：

- TaskHub 不创建本地 `users` 表。
- 当前登录人员工号来自公司 SSO。
- TaskHub 业务角色来自本地 `taskhub_user_role` 表。
- 公司 SSO 当前登录人接口返回 JSON：第一层包含 `code`、`data`、`msg`、`timestamp`、`total`，人员字段在 `data[0]` 下。
- 公司 token 接口使用 `application/x-www-form-urlencoded`。
- 拿到 access token 之后，请求当前登录人等受保护接口时，Header 必须增加 `Authorization: bearer {token}`。

## 涉及文件

| 文件 | 作用 |
|---|---|
| `.env.example` | 提供 SSO 配置占位 |
| `config/sso.php` | 统一读取 SSO 配置 |
| `app/Integrations/Sso/SsoException.php` | SSO 异常 |
| `app/Integrations/Sso/SsoToken.php` | token 响应值对象 |
| `app/Integrations/Sso/SsoUser.php` | 当前登录人值对象 |
| `app/Integrations/Sso/SsoClient.php` | 调用公司 SSO token 和用户信息接口 |
| `app/Services/CurrentUserService.php` | 统一获取当前登录人和角色 |
| `app/Services/TaskhubRoleService.php` | 从数据库读取 TaskHub 角色 |
| `app/Http/Middleware/EnsureSsoAuthenticated.php` | 保护业务页面 |
| `app/Http/Controllers/SsoController.php` | 登录、回调、退出 |
| `resources/js/Pages/Sso/Callback.tsx` | 回调失败提示页 |
| `routes/web.php` | SSO 路由 |
| `tests/Feature/ApplicationShellTest.php` | 认证流程测试 |

## 实际执行命令

命令执行目录：项目根目录。

```bash
# 创建 SSO 目录。
mkdir -p app/Integrations/Sso

# 创建 SSO Controller。
php artisan make:controller SsoController

# 创建 SSO 登录检查中间件。
php artisan make:middleware EnsureSsoAuthenticated
```

值对象和 Client 没有 Laravel 官方生成命令，手动创建明确文件：

```bash
touch app/Integrations/Sso/SsoException.php
touch app/Integrations/Sso/SsoToken.php
touch app/Integrations/Sso/SsoUser.php
touch app/Integrations/Sso/SsoClient.php
```

验证命令：

```bash
vendor/bin/pint --dirty
php artisan test
npm run typecheck
npm run build
```

## 第 1 步：配置环境变量

文件：

```text
.env.example
```

确认包含：

```env
# SSO：授权码模式。浏览器只拿 code，Laravel 后端用 code 换 access_token，再查当前登录人。
SSO_BASE_URL=
SSO_LOGIN_URL=
SSO_LOGOUT_URL=
SSO_CLIENT_ID=
SSO_CLIENT_SECRET=
SSO_SCOPE=
SSO_CALLBACK_PATH=/sso/callback
SSO_TOKEN_PATH=
SSO_USERINFO_PATH=
SSO_VALIDATE_PATH=
SSO_TIMEOUT=3
SSO_VERIFY_SSL=false
```

字段说明：

- `SSO_BASE_URL`：公司 SSO 服务基础地址，例如 `https://sso.company.com`。
- `SSO_LOGIN_URL`：浏览器跳转的 authorize 完整地址。
- `SSO_TOKEN_PATH`：授权码换 token 的接口路径，例如 `/auth/oauth/token`。
- `SSO_USERINFO_PATH`：用 accessToken 查询当前登录人的接口路径。
- `SSO_CLIENT_ID`：公司分配给 TaskHub 的客户端 ID。
- `SSO_CLIENT_SECRET`：公司分配给 TaskHub 的客户端密钥，只能放后端。
- `SSO_CALLBACK_PATH`：TaskHub 接收 SSO 回调的站内路径。
- `SSO_LOGOUT_URL`：公司 SSO 退出地址，没有时可以为空。

注意：

- `SSO_TOKEN_PATH` 和 `SSO_USERINFO_PATH` 只写 path，不写完整 URL。
- 完整域名统一放在 `SSO_BASE_URL`。
- 不要把 `SSO_CLIENT_SECRET` 写进 React、TypeScript 或浏览器可见代码。

## 第 2 步：创建 config/sso.php

文件：

```text
config/sso.php
```

完整内容：

```php
<?php

return [
    // 公司 SSO 服务基础地址，只放域名和公共前缀，例如 https://sso.example.com。
    'base_url' => env('SSO_BASE_URL'),
    // 浏览器登录跳转完整地址；它通常是公司 SSO 的 authorize 地址。
    'login_url' => env('SSO_LOGIN_URL'),
    // 公司统一退出地址；为空时只退出 TaskHub 本地 Session。
    'logout_url' => env('SSO_LOGOUT_URL'),
    // 公司分配给 TaskHub 的客户端标识。
    'client_id' => env('SSO_CLIENT_ID'),
    // 后端调用 token 和人员信息接口时使用，绝不能暴露给前端。
    'client_secret' => env('SSO_CLIENT_SECRET'),
    // SSO 登录时请求的权限范围；图片示例为 all。
    'scope' => env('SSO_SCOPE'),
    // SSO 回调到 TaskHub 的站内 path。
    'callback_path' => env('SSO_CALLBACK_PATH', '/sso/callback'),
    // 使用授权码 code 换取 access_token 的接口 path，只写 path，不写完整 URL。
    'token_path' => env('SSO_TOKEN_PATH'),
    // 推荐使用的当前登录人接口 path，只写 path，不写完整 URL。
    'userinfo_path' => env('SSO_USERINFO_PATH'),
    // 当前登录人接口请求方法；新接口通过 Authorization Header 识别用户，默认 GET。
    'userinfo_method' => env('SSO_USERINFO_METHOD', 'GET'),
    // 早期文档中的 token 校验 path，保留兼容，优先级低于 userinfo_path。
    'validate_path' => env('SSO_VALIDATE_PATH'),
    // 公司接口调用超时时间，避免登录请求长时间挂起。
    'timeout' => (int) env('SSO_TIMEOUT', 3),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('SSO_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];
```

修改 `.env` 后需要清理配置缓存：

```bash
php artisan config:clear
```

## 第 3 步：创建 SsoToken

文件：

```text
app/Integrations/Sso/SsoToken.php
```

作用：

- 表示公司 token 接口返回的数据。
- 从响应中取出 `access_token`。
- 根据 JWT 第二段 `exp` 或 `expires_in` 计算过期时间。
- 生成可以写入 Session 的数组。

关键逻辑：

```php
// token 接口标准返回结构：{"code":"00000","data":{"access_token":"..."}}
$data = isset($payload['data']) && is_array($payload['data']) ? $payload['data'] : $payload;

// 兼容 access_token 和 accessToken 两种命名。
$accessToken = $data['access_token'] ?? $data['accessToken'] ?? null;

// access_token 可能是 JWT：header.payload.signature。
// 第二段 payload 解码后如果有 exp，就可以得到真实过期时间。
$segments = explode('.', $accessToken);
```

为什么要单独建 `SsoToken`：

- Controller 不应该知道 token 响应所有字段。
- token 过期判断是认证基础能力，应该集中管理。
- Session 保存数组，不保存 PHP 对象，避免未来类结构变化影响旧 Session。

## 第 4 步：创建 SsoUser

文件：

```text
app/Integrations/Sso/SsoUser.php
```

公司当前登录人接口结构：

```json
{
  "code": "",
  "data": [
    {
      "deptInfoList": [
        {
          "obiCode": "DEV01",
          "obiName": "开发一部",
          "obiUuid": "dept-uuid"
        }
      ],
      "empCnNum": "E10001",
      "empEmail": "zhangsan@example.com",
      "empJpNum": "JP10001",
      "empName": "张三",
      "empNameCn": "张三",
      "empNameEn": "Zhang San",
      "empPhoto": "",
      "empPosition": "工程师",
      "empSex": "M",
      "empUserName": "zhangsan",
      "empWorkStatus": "在职",
      "id": "employee-row-id"
    }
  ],
  "msg": "",
  "timestamp": 0,
  "total": 1
}
```

解析规则：

```php
// 新接口返回 data 数组，当前登录人信息取第一条。
$user = isset($payload['data'][0]) && is_array($payload['data'][0])
    ? $payload['data'][0]
    : $payload;

// TaskHub 所有人员引用字段统一使用工号。
$employeeNo = $user['empCnNum'] ?? $user['empNumCn'] ?? $user['employeeNo'] ?? null;
```

注意：

- `SsoUser` 不是数据库 Model。
- 它不对应 `users` 表。
- 它只表示“总部 SSO 当前登录人接口返回的人”。
- `displayName` 优先取 `empName`，其次取 `empNameCn`。
- `departmentId` 优先取 `deptInfoList[0].obiCode`。
- `departmentName` 优先取 `deptInfoList[0].obiName`，缺失时再使用人员记录中的 `department`。
- 本据点更准确的人员信息会放在 Session 的 `sso_user.siteUser` 中，不覆盖总部原始信息。

## 第 5 步：创建 SsoClient

文件：

```text
app/Integrations/Sso/SsoClient.php
```

`SsoClient` 负责两个真实外部请求。

第一个请求：用 `code` 换 `access_token`。

```php
$response = $request->post($tokenPath, [
    // code 是公司 SSO 回调到 /sso/callback 时带回来的授权码。
    'code' => $code,
    // client_id/client_secret 由公司 SSO 分配，必须来自后端配置。
    'client_id' => $clientId,
    'client_secret' => $clientSecret,
    // redirect_uri 必须和发起授权请求时传给 SSO 的回调地址完全一致。
    'redirect_uri' => $redirectUri,
    // 授权码模式固定使用 authorization_code。
    'grant_type' => 'authorization_code',
]);
```

必须使用：

```php
->asForm()
```

原因是图片中的 token 接口要求：

```text
content-type: application/x-www-form-urlencoded
```

第二个请求：用 `accessToken` 查询当前登录人。

```php
$request = Http::baseUrl($baseUrl)
    ->timeout((int) config('sso.timeout', 3))
    ->acceptJson()
    // 当前人员信息接口要求在 Header 中携带 Authorization。
    // 注意这里按公司要求使用小写 bearer。
    ->withHeaders([
        'Authorization' => 'bearer '.$accessToken,
    ]);

$response = match ($method) {
    'POST' => $request->asJson()->post($userInfoPath),
    'GET' => $request->get($userInfoPath),
};
```

当前登录人接口已经改为通过 Header 识别用户，默认使用 `GET`。如果公司实际接口要求 `POST`，在 `.env` 中设置：

```env
SSO_USERINFO_METHOD=POST
```

注意：

- token 接口还没有 access token，因此 token 接口不加 `Authorization`。
- 拿到 access token 之后调用的受保护接口，都应该使用 `Authorization: bearer {token}`。
- 当前登录人接口不再依赖前端传 token，也不把 `clientSecret` 暴露给浏览器。

为什么不在 Controller 中直接写 `Http::post()`：

- Controller 应该编排登录流程，不应该关心公司接口细节。
- token 接口是 form，userinfo 接口是 Header 鉴权，两者差异集中在 Client 更清晰。
- 测试可以替换 `SsoClient`，不用访问真实公司接口。

## 第 6 步：修改 CurrentUserService

文件：

```text
app/Services/CurrentUserService.php
```

增加 token Session key：

```php
public const TOKEN_SESSION_KEY = 'sso_token';
```

作用：

- `sso_user` 保存当前登录人快照。
- `taskhub_roles` 保存 TaskHub 本地业务角色。
- `sso_token` 保存 access_token 快照和过期时间。

为什么不只保存 `sso_user`：

- 图片要求客户端需要判断是否登录、令牌是否过期。
- 在 Laravel + Inertia 单体中，这个判断放在后端 Middleware 更合适。
- 用户信息还在但 token 已过期时，应重新走 SSO。

## 第 7 步：修改 EnsureSsoAuthenticated

文件：

```text
app/Http/Middleware/EnsureSsoAuthenticated.php
```

核心判断：

```php
if (
    $request->session()->has(CurrentUserService::SESSION_KEY)
    && SsoToken::sessionPayloadIsValid($request->session()->get(CurrentUserService::TOKEN_SESSION_KEY))
) {
    return $next($request);
}
```

逻辑说明：

1. 有 `sso_user`，表示曾经登录成功。
2. `sso_token` 仍有效，表示 access_token 没过期。
3. 两个条件都满足，才能进入业务页面。
4. 任一条件不满足，清理旧 Session 并跳转 `/login`。

为什么 Middleware 不直接刷新 token：

- 当前 MVP 先不实现 refresh_token 流程。
- 自动刷新涉及失败重试、并发请求、refresh token 生命周期等问题。
- 当前更简单可靠：token 过期后重新走公司 SSO。

## 第 8 步：修改 SsoController

文件：

```text
app/Http/Controllers/SsoController.php
```

### 8.1 redirect：发起授权请求

关键代码：

```php
$query = http_build_query(array_filter([
    // 授权码模式固定传 code，不能再使用隐式模式的 token。
    'response_type' => 'code',
    'client_id' => $clientId,
    // Laravel 生成绝对回调地址，避免不同环境手写 callback URL。
    'redirect_uri' => route('sso.callback'),
    'scope' => config('sso.scope'),
], fn (mixed $value): bool => is_string($value) && $value !== ''));

return redirect()->away($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').$query);
```

最终跳转地址类似：

```text
http://认证服务器IP/auth/oauth/authorize?client_id=TaskHub&response_type=code&scope=all&redirect_uri=http%3A%2F%2F127.0.0.1%3A8000%2Fsso%2Fcallback
```

注意：

- 图片里 `state` 是携带参数。当前没有明确要求必须校验，因此本阶段不强制使用。
- 如果后续公司要求传 `state`，需要同时保存 Session 并在 callback 校验。

### 8.2 callback：处理 code

关键代码：

```php
$code = $request->query('code');

if (! is_string($code) || $code === '') {
    return Inertia::render('Sso/Callback', [
        'status' => 'failed',
        'message' => 'SSO 回调缺少 code。',
    ]);
}

$token = $ssoClient->exchangeCodeForToken($code, route('sso.callback'));
$user = $ssoClient->fetchCurrentUser($token->accessToken());

$this->establishSession($request, $user, $token, $personnelClient, $roleService);

return redirect()->intended(route('tasks.index'));
```

为什么 callback 不返回 JSON：

- 授权码模式是浏览器页面导航。
- 公司 SSO 回调到 TaskHub 后，Laravel 后端直接完成登录。
- 成功后直接 302 到原业务页面。
- 失败时才返回 React 错误提示页。

### 8.3 establishSession：写入本地 Session

Session 写入：

```php
$request->session()->put(CurrentUserService::SESSION_KEY, $sessionUser);
$request->session()->put(CurrentUserService::ROLE_SESSION_KEY, $roles);
$request->session()->put(CurrentUserService::TOKEN_SESSION_KEY, $token->toSessionPayload());
$request->session()->regenerate();
```

为什么登录成功后要 `regenerate()`：

- 登录前后 Session ID 应变化。
- 可以降低会话固定攻击风险。
- 这是 Web 登录流程的常规安全动作。

## 第 9 步：修改回调 React 页面

文件：

```text
resources/js/Pages/Sso/Callback.tsx
```

授权码模式下，React 回调页只负责展示失败提示。

完整内容：

```tsx
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';

type SsoCallbackProps = {
    // status 由 Laravel SsoController::callback 传入。
    // 授权码模式成功时后端会直接 redirect，不会停留在这个页面。
    status?: 'failed';
    // message 是后端给出的失败原因，例如缺少 code 或 token 接口请求失败。
    message?: string;
};

export default function SsoCallback({ message, status = 'failed' }: SsoCallbackProps) {
    return (
        <main className="flex min-h-screen items-center justify-center bg-[#fafafa] px-6 text-[#1a1a1a]">
            <Card as="section" className="w-full max-w-md border-[#ebebeb] shadow-sm">
                <CardContent className="p-6">
                    {/* 这里不是完整登录页，只是 SSO 授权码回调失败时的提示页。 */}
                    <div className="mb-4 flex items-center gap-3">
                        <div className="flex size-8 items-center justify-center rounded-md bg-[#5e6ad2] text-sm font-bold text-white">
                            T
                        </div>
                        <div>
                            <h1 className="m-0 text-lg font-semibold">TaskHub SSO</h1>
                            <p className="mt-1 text-sm text-[#6e6e80]">
                                {status === 'failed' ? '登录未完成' : '正在完成单点登录'}
                            </p>
                        </div>
                    </div>

                    <p className="text-sm leading-6 text-[#6e6e80]">{message ?? 'SSO 授权码登录失败。'}</p>

                    <Button asChild className="mt-5">
                        <a href="/login">重新登录</a>
                    </Button>
                </CardContent>
            </Card>
        </main>
    );
}
```

为什么删除前端 `fetch('/sso/session')`：

- 隐式模式才会让浏览器拿到 `access_token`。
- 授权码模式中，浏览器只拿 `code`。
- `code` 换 token 必须带 `client_secret`，只能在后端执行。
- React 不能也不应该接触 `client_secret` 和 `access_token`。

## 第 10 步：修改 routes/web.php

文件：

```text
routes/web.php
```

SSO 相关路由：

```php
// 登录入口：浏览器跳转到公司 SSO。
Route::get('/login', [SsoController::class, 'redirect'])->name('sso.login');

// SSO 授权码回调路径可配置，Laravel 后端会在这里用 code 换 access_token 并建立 Session。
Route::get(config('sso.callback_path', '/sso/callback'), [SsoController::class, 'callback'])->name('sso.callback');

// 退出必须走原生 POST 表单，避免 Inertia Ajax 跟随外部 SSO 302 造成 CORS。
Route::post('/logout', [SsoController::class, 'logout'])->name('sso.logout');
```

不再需要：

```php
Route::post('/sso/session', [SsoController::class, 'store'])->name('sso.session.store');
```

原因：

- 授权码模式下后端 callback 已经完成 Session 建立。
- 前端不再把 token 提交给 Laravel。
- 删除该接口可以减少误用和安全风险。

## 第 11 步：退出流程保持原生表单

退出按钮仍使用原生 POST Form：

```tsx
<form action="/logout" method="POST">
    <input name="_token" type="hidden" value={csrfToken()} />
    <button type="submit">退出</button>
</form>
```

原因：

- 退出后可能跳转公司外部 SSO logout URL。
- Inertia Ajax 跟随外部 302 会触发浏览器 CORS。
- 原生表单提交会作为浏览器顶层导航，不会走 Ajax。

## 第 12 步：测试

重点测试：

- `/sso/callback` 缺少 code 时返回错误提示页。
- `/sso/callback?code=...` 会调用 `exchangeCodeForToken()`。
- 换到 token 后会调用 `fetchCurrentUser()`。
- Session 中写入 `sso_user`、`sso_token`、`taskhub_roles`。
- token 接口使用 `application/x-www-form-urlencoded`。
- 当前登录人接口使用 `Authorization: bearer {token}`。
- 当前登录人接口返回 `code + data[]`，由 `SsoUser::fromPayload()` 解析 `data[0]`。
- 退出时清理用户、角色和 token。

执行：

```bash
php artisan test
```

## 第 13 步：手动验证

### 13.1 配置 .env

```env
SSO_BASE_URL=http://认证服务器IP
SSO_LOGIN_URL=http://认证服务器IP/auth/oauth/authorize
SSO_CLIENT_ID=你的客户端ID
SSO_CLIENT_SECRET=你的客户端密钥
SSO_SCOPE=all
SSO_CALLBACK_PATH=/sso/callback
SSO_TOKEN_PATH=/auth/oauth/token
SSO_USERINFO_PATH=/你的当前登录人接口路径
SSO_USERINFO_METHOD=GET
SSO_TIMEOUT=3
SSO_VERIFY_SSL=false
```

然后执行：

```bash
php artisan config:clear
php artisan cache:clear
```

### 13.2 启动项目

```bash
composer run dev
```

访问：

```text
http://127.0.0.1:8000/tasks
```

预期：

- 未登录时跳转公司 SSO。
- 公司 SSO 登录后回调 `/sso/callback?code=...`。
- TaskHub 自动进入 `/tasks`。

### 13.3 查看日志

如果 token 或人员信息接口失败，查看：

```text
storage/logs/laravel.log
```

不要把 `client_secret` 或完整 `access_token` 打到日志里。

## 常见错误

| 错误 | 常见原因 | 处理方式 |
|---|---|---|
| `SSO callback missing code` | 公司 SSO 没有回调 `?code=...` | 检查 authorize 地址的 `response_type=code` 和回调配置 |
| `SSO token path is not configured` | `.env` 缺少 `SSO_TOKEN_PATH` | 增加 `SSO_TOKEN_PATH=/auth/oauth/token` 后执行 `php artisan config:clear` |
| `SSO token path must be a path` | `SSO_TOKEN_PATH` 写成完整 URL | 域名放 `SSO_BASE_URL`，path 只写 `/auth/oauth/token` |
| token 接口 400 | `redirect_uri` 和注册地址不一致 | 确认 `/login` 和 token 请求里的 `redirect_uri` 完全一致 |
| token 接口 401 | `client_id` 或 `client_secret` 错误 | 核对公司分配的客户端信息 |
| 当前登录人接口失败 | `SSO_USERINFO_PATH`、`SSO_USERINFO_METHOD` 或 Authorization Header 不符合协议 | 确认请求 Header 是 `Authorization: bearer {token}` |
| 登录成功后又跳回登录 | Session 没写入或 token 已过期 | 检查 `SESSION_DRIVER=file`，并查看 Session 中是否有 `sso_token.expiresAt` |
| 退出 CORS | 使用 Inertia Ajax 调 `/logout` | 退出必须使用原生 POST Form |

## 与 Spring Boot 对照

| Laravel | Spring Boot | 说明 |
|---|---|---|
| `routes/web.php` | `@GetMapping` / `@PostMapping` | 定义登录、回调、退出路由 |
| `SsoController` | `@Controller` | 编排登录跳转、回调、Session 写入 |
| `SsoClient` | `RestTemplate` / `WebClient` 封装类 | 调外部 SSO 接口 |
| `SsoToken` | Token DTO | 表示 token 响应 |
| `SsoUser` | User DTO | 表示当前登录人响应 |
| `EnsureSsoAuthenticated` | Filter / HandlerInterceptor | 保护业务页面 |
| Laravel Session | HttpSession | 保存本地登录态 |
| `redirect()->away()` | `redirect:` 外部地址 | 跳转公司 SSO |

## 本章总结

本章把 TaskHub SSO 从隐式模式调整为授权码模式。

关键变化：

- `/login` 使用 `response_type=code`。
- `/sso/callback` 由 Laravel 后端直接处理 `code`。
- 新增 `SsoToken` 保存 token 响应和过期时间。
- 新增 `SSO_TOKEN_PATH`。
- 删除前端读取 `access_token` 和 `POST /sso/session` 的流程。
- `EnsureSsoAuthenticated` 同时检查 `sso_user` 和 `sso_token`。
- `client_secret` 不暴露给前端。

## 操作检查清单

- [ ] `.env` 已配置 `SSO_TOKEN_PATH`。
- [ ] `/login` 跳转地址包含 `response_type=code`。
- [ ] `/sso/callback?code=...` 能换取 token。
- [ ] token 接口请求是 `application/x-www-form-urlencoded`。
- [ ] 当前登录人接口仍由 Laravel 后端调用。
- [ ] React 回调页不读取 `access_token`。
- [ ] `/sso/session` 路由已删除。
- [ ] Session 中有 `sso_user`、`sso_token`、`taskhub_roles`。
- [ ] 退出继续使用原生 POST Form。
- [ ] `php artisan test` 通过。
- [ ] `npm run typecheck` 通过。
- [ ] `npm run build` 通过。

## 下一章入口

继续阅读：

[10-任务大厅与业务布局](./10-任务大厅与业务布局.md)

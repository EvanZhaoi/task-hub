# 09-SSO隐式模式接入

## 1. 本章目标

本章完成 TaskHub 的 SSO 认证骨架。

这一章不是做任务发布、投标、交付等业务功能，而是把“用户如何登录 TaskHub、TaskHub 如何知道当前登录人是谁、TaskHub 如何控制业务角色”这条基础链路搭好。

最终链路：

```text
浏览器访问 /tasks
↓
EnsureSsoAuthenticated 检查 Laravel Session
↓
未登录，跳转 /login
↓
SsoController 拼接公司 SSO 登录地址
↓
公司 SSO 登录成功后回调 /sso/callback?access_token=...
↓
React 回调页读取 query string 中的 access_token
↓
React POST /sso/session
↓
Laravel 后端用 accessToken 调公司当前登录人接口
↓
拿到 employeeNo / displayName / department
↓
根据 employeeNo 查询 taskhub_user_role
↓
把用户信息和角色写入 Laravel Session
↓
浏览器回到原本要访问的业务页面
```

已经确认的公司 SSO 规则：

- 回调参数使用 query string：`/sso/callback?access_token=xxx`。
- 不使用 URL fragment：不是 `#access_token=xxx`。
- 不支持 `state` 回传和校验。
- TaskHub 拿到 `accessToken` 后，必须由后端调用公司接口获取当前登录人信息。

后续如果公司 SSO 协议还有不确定项，例如登录 URL、参数名、用户信息接口路径、返回 JSON 结构，不要猜，先确认协议再改代码。

## 2. 学习目标

完成本章后，你应该理解：

- Laravel + Inertia 单体应用为什么使用 Session 保存浏览器登录态。
- SSO 只解决“你是谁”，TaskHub 角色解决“你能做什么”。
- 为什么 TaskHub 不创建本地 `users` 表。
- 为什么前端不能决定当前用户身份和角色。
- 为什么 Controller 不应该到处直接读 Session，而要通过统一服务。
- Middleware 如何保护业务页面。
- React 回调页在 SSO 流程中负责什么。

## 3. 最终效果

访问受保护页面：

```text
http://127.0.0.1:8000/tasks
```

未登录时：

1. `/tasks` 被 `EnsureSsoAuthenticated` 拦截。
2. Laravel 保存原始访问地址。
3. 浏览器跳转到 `/login`。
4. `/login` 再跳转到公司 SSO。
5. 公司 SSO 登录成功后回调 `/sso/callback?access_token=xxx`。
6. React 回调页把 `accessToken` 提交给 Laravel。
7. Laravel 调公司接口获取当前登录人。
8. Laravel 写入 Session。
9. 浏览器回到 `/tasks`。

没有公司真实 SSO 参数时，无法完成真实登录，但可以验证：

- 路由存在。
- 回调页能打开。
- 前端能编译。
- 后端测试通过。
- `/tasks` 未登录会跳转 `/login`。

## 4. 涉及文件

本章新增或修改这些文件：

```text
.env.example
config/sso.php
config/taskhub.php
app/Integrations/Sso/SsoException.php
app/Integrations/Sso/SsoUser.php
app/Integrations/Sso/SsoClient.php
app/Services/CurrentUserService.php
app/Services/TaskhubRoleService.php
app/Http/Middleware/EnsureSsoAuthenticated.php
app/Http/Middleware/HandleInertiaRequests.php
app/Http/Controllers/SsoController.php
routes/web.php
resources/views/app.blade.php
resources/js/Pages/Sso/Callback.tsx
tests/Feature/ApplicationShellTest.php
database/schema.sql
```

`database/schema.sql` 中已经存在 `taskhub_user_role` 表，本章不重新设计数据库。

## 5. 前置条件

命令执行目录：项目根目录。

确认基础项目已经可以运行：

```bash
composer install
npm install
php artisan key:generate
npm run typecheck
npm run build
php artisan test
```

确认数据库结构已经初始化：

```bash
mysql -u root -p taskhub < database/schema.sql
```

如果你的 MySQL 用户、库名、密码不是这个示例，按 `04-数据库初始化与配置.md` 中的方式调整。

## 6. 第一步：补充 SSO 环境变量模板

命令执行目录：项目根目录。

打开：

```text
.env.example
```

确认包含下面字段：

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

这些字段的作用：

| 字段 | 作用 |
|---|---|
| `SSO_BASE_URL` | 公司 SSO 接口基础地址，例如用户信息接口所在域名 |
| `SSO_LOGIN_URL` | 公司 SSO 登录入口 |
| `SSO_CLIENT_ID` | 公司分配给 TaskHub 的客户端标识 |
| `SSO_SCOPE` | 公司协议要求的授权范围，没有则留空 |
| `SSO_CALLBACK_PATH` | 公司 SSO 登录成功后回调 TaskHub 的路径 |
| `SSO_USERINFO_PATH` | TaskHub 后端用 accessToken 获取当前登录人的接口路径 |
| `SSO_VALIDATE_PATH` | 早期兼容命名；没有 `SSO_USERINFO_PATH` 时才回退使用 |
| `SSO_TIMEOUT` | 后端调用公司 SSO 接口的超时时间，单位秒 |

然后打开你本地的：

```text
.env
```

把同样字段补进去，并按公司 SSO 文档填写真实值。

注意：

- `.env` 不提交到 Git。
- `.env.example` 是模板，不会自动同步到已有 `.env`。
- 如果你之前已经复制过 `.env`，新增字段需要手动补。

修改 `.env` 后执行：

```bash
php artisan config:clear
php artisan cache:clear
```

为什么要执行：

- Laravel 会缓存配置。
- `.env` 改完后，如果不清缓存，代码可能仍读取旧配置。

验证：

```bash
php artisan tinker
```

进入 Tinker 后执行：

```php
config('sso.callback_path')
```

预期输出：

```text
=> "/sso/callback"
```

输入 `exit` 退出 Tinker。

## 7. 第二步：创建 SSO 配置文件

命令执行目录：项目根目录。

创建文件：

```text
config/sso.php
```

完整内容：

```php
<?php

return [
    'base_url' => env('SSO_BASE_URL'),
    'login_url' => env('SSO_LOGIN_URL'),
    'client_id' => env('SSO_CLIENT_ID'),
    'scope' => env('SSO_SCOPE'),
    'callback_path' => env('SSO_CALLBACK_PATH', '/sso/callback'),
    'userinfo_path' => env('SSO_USERINFO_PATH'),
    'validate_path' => env('SSO_VALIDATE_PATH'),
    'timeout' => (int) env('SSO_TIMEOUT', 5),
];
```

为什么要建 `config/sso.php`：

- Controller 和 Client 不直接读 `env()`。
- Laravel 推荐运行时代码读取 `config()`。
- 以后生产环境可以使用配置缓存。

和 Spring Boot 的类比：

```text
config/sso.php
≈ application.yml 中的 sso 配置段
≈ @ConfigurationProperties(prefix = "sso")
```

区别是 Laravel 默认使用 PHP 数组作为配置文件。

验证：

```bash
php artisan tinker
```

执行：

```php
config('sso.timeout')
```

预期输出：

```text
=> 5
```

## 8. 第三步：创建 TaskHub 角色配置

命令执行目录：项目根目录。

创建文件：

```text
config/taskhub.php
```

完整内容：

```php
<?php

return [
    'roles' => ['DEVELOPER', 'PUBLISHER', 'BOSS'],
];
```

注意：角色授权关系不放在 `.env`，而是放在数据库表 `taskhub_user_role`。

这里的 `config/taskhub.php` 只保存系统支持哪些角色值：

- `DEVELOPER`：开发者。
- `PUBLISHER`：发布者。
- `BOSS`：老板或管理视角。

为什么角色关系不放 `.env`：

- 角色是业务配置，不是部署配置。
- 变更角色不应该要求重新发布应用。
- 数据库更适合维护“某个工号拥有什么角色”。

## 9. 第四步：确认角色表和 Model

命令执行目录：项目根目录。

SQL 已在：

```text
database/schema.sql
```

角色表核心结构：

```sql
CREATE TABLE `taskhub_user_role` (
  `id` BIGINT UNSIGNED NOT NULL COMMENT '主键ID',
  `employee_no` VARCHAR(32) NOT NULL COMMENT '人员工号（外部人员接口标识）',
  `role` VARCHAR(20) NOT NULL COMMENT 'TaskHub业务角色',
  `enabled` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '是否启用：1启用，0禁用',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `UNIQ_taskhub_user_role_employee_role` (`employee_no`, `role`),
  KEY `IDX_taskhub_user_role_employee_enabled` (`employee_no`, `enabled`),
  CONSTRAINT `CHK_taskhub_user_role_role` CHECK (`role` IN ('DEVELOPER', 'PUBLISHER', 'BOSS')),
  CONSTRAINT `CHK_taskhub_user_role_enabled` CHECK (`enabled` IN (0, 1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci COMMENT='TaskHub用户角色表';
```

创建 Model：

```bash
php artisan make:model TaskhubUserRole
```

文件：

```text
app/Models/TaskhubUserRole.php
```

完整内容：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaskhubUserRole extends Model
{
    protected $table = 'taskhub_user_role';

    public $incrementing = false;

    protected $keyType = 'int';

    protected $fillable = [
        'id',
        'employee_no',
        'role',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
        ];
    }
}
```

为什么需要这个 Model：

- Eloquent 通过 Model 查询数据库。
- `TaskhubRoleService` 会通过它查某个工号的启用角色。
- 不创建 `users` 表，不代表不能为角色表创建 Model。

给测试人员或开发者加角色：

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

验证：

```bash
php artisan tinker
```

执行：

```php
App\Models\TaskhubUserRole::query()->where('employee_no', 'E10001')->pluck('role')
```

如果没有插入测试数据，返回空集合是正常的。

## 10. 第五步：创建 SSO 目录结构

命令执行目录：项目根目录。

执行：

```bash
mkdir -p app/Integrations/Sso
mkdir -p app/Services
```

目录职责：

```text
app/Integrations/Sso
```

放公司 SSO 相关接入代码。

```text
app/Services
```

放项目内可复用的业务服务。本章只放当前用户服务和角色解析服务，不引入 Repository、DDD、CQRS。

## 11. 第六步：创建 SSO 异常类

文件：

```text
app/Integrations/Sso/SsoException.php
```

完整内容：

```php
<?php

namespace App\Integrations\Sso;

use RuntimeException;

class SsoException extends RuntimeException
{
}
```

为什么需要：

- SSO 登录失败、接口不可用、返回缺少工号，都属于 SSO 接入异常。
- 使用专门异常类后，Controller 可以准确捕获并返回登录失败。

Java 类比：

```text
SsoException
≈ 自定义 RuntimeException
```

## 12. 第七步：创建 SSO 用户对象

文件：

```text
app/Integrations/Sso/SsoUser.php
```

完整内容：

```php
<?php

namespace App\Integrations\Sso;

final readonly class SsoUser
{
    public function __construct(
        private string $employeeNo,
        private ?string $displayName = null,
        private ?string $departmentId = null,
        private ?string $departmentName = null,
        private array $raw = [],
    ) {}

    public static function fromPayload(array $payload): self
    {
        $employeeNo = $payload['employeeNo'] ?? $payload['employee_no'] ?? null;

        if (! is_string($employeeNo) || $employeeNo === '') {
            throw new SsoException('SSO response does not contain employee number.');
        }

        return new self(
            employeeNo: $employeeNo,
            displayName: self::nullableString($payload['displayName'] ?? $payload['display_name'] ?? null),
            departmentId: self::nullableString($payload['departmentId'] ?? $payload['department_id'] ?? null),
            departmentName: self::nullableString($payload['departmentName'] ?? $payload['department_name'] ?? null),
            raw: $payload,
        );
    }

    public function employeeNo(): string
    {
        return $this->employeeNo;
    }

    public function displayName(): ?string
    {
        return $this->displayName;
    }

    public function departmentId(): ?string
    {
        return $this->departmentId;
    }

    public function departmentName(): ?string
    {
        return $this->departmentName;
    }

    public function raw(): array
    {
        return $this->raw;
    }

    public function toSessionPayload(): array
    {
        return [
            'employeeNo' => $this->employeeNo,
            'displayName' => $this->displayName,
            'departmentId' => $this->departmentId,
            'departmentName' => $this->departmentName,
            'raw' => $this->raw,
        ];
    }

    private static function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
```

为什么不用数组到处传：

- 数组字段名容易写错。
- 业务代码需要稳定读取 `employeeNo()`。
- 后续公司接口字段变化时，只改 `fromPayload()`。

这里兼容两种字段名：

```text
employeeNo
employee_no
```

原因是公司接口真实返回结构还需要最终确认。确认后可以收紧解析规则。

## 13. 第八步：创建 SSO Client

文件：

```text
app/Integrations/Sso/SsoClient.php
```

完整内容：

```php
<?php

namespace App\Integrations\Sso;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class SsoClient
{
    public function fetchCurrentUser(string $accessToken): SsoUser
    {
        // SSO 基础地址和接口路径属于公司协议配置，不在代码中写死，便于不同环境切换。
        $baseUrl = config('sso.base_url');

        if (! is_string($baseUrl) || $baseUrl === '') {
            throw new SsoException('SSO base URL is not configured.');
        }

        // userinfo_path 是当前推荐命名；validate_path 保留为早期配置兼容入口。
        $userInfoPath = config('sso.userinfo_path') ?: config('sso.validate_path');

        if (! is_string($userInfoPath) || $userInfoPath === '') {
            throw new SsoException('SSO user info path is not configured.');
        }

        try {
            // TODO: 按公司 SSO 文档确认真实请求方法、路径、Header 和返回结构。
            // 当前骨架表达的是关键安全边界：后端用 accessToken 换取当前登录人信息。
            $response = Http::baseUrl($baseUrl)
                ->timeout((int) config('sso.timeout', 5))
                ->acceptJson()
                ->withToken($accessToken)
                ->get($userInfoPath);
        } catch (ConnectionException $exception) {
            throw new SsoException('Unable to connect to SSO user info service.', previous: $exception);
        }

        if (! $response->successful()) {
            throw new SsoException(sprintf('SSO user info request failed with HTTP status %d.', $response->status()));
        }

        $payload = $response->json();

        // 当前登录人接口必须返回 JSON 对象，后续由 SsoUser 统一解析 employeeNo 等字段。
        if (! is_array($payload)) {
            throw new SsoException('SSO user info response is not a JSON object.');
        }

        return SsoUser::fromPayload($payload);
    }

    public function validateToken(string $token): SsoUser
    {
        // 预留给 Bearer Token API 场景；当前 Inertia 页面登录主要使用 fetchCurrentUser。
        return $this->fetchCurrentUser($token);
    }
}
```

关键点：

- 前端不能把姓名、部门、角色直接传给后端。
- 后端必须用 `accessToken` 调公司接口确认当前登录人。
- `TODO` 保留在真实公司协议位置，不编造接口。

Laravel 知识点：

```php
Http::baseUrl($baseUrl)
```

是 Laravel HTTP Client，类似 Java 里的 `RestTemplate`、`WebClient` 或 OpenFeign。

## 14. 第九步：创建当前用户服务

文件：

```text
app/Services/CurrentUserService.php
```

完整内容：

```php
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

    public function __construct(
        private readonly Request $request,
        private readonly SsoClient $ssoClient,
    ) {}

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

    public function employeeNo(): string
    {
        return $this->user()->employeeNo();
    }

    public function roles(): array
    {
        // TaskHub 业务角色在登录时写入 Session，来源是 taskhub_user_role 表。
        // 这里做一次类型过滤，避免 Session 中出现非字符串值影响权限判断。
        $roles = $this->request->session()->get(self::ROLE_SESSION_KEY, []);

        return is_array($roles) ? array_values(array_filter($roles, 'is_string')) : [];
    }

    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles(), true);
    }
}
```

为什么要这个服务：

- 后续 Controller 不直接读 `Session::get('sso_user')`。
- 当前登录人的读取方式集中在一个地方。
- 以后如果公司 SSO 逻辑变化，业务代码不需要大面积修改。

Java 类比：

```text
CurrentUserService
≈ Spring Security 中封装 SecurityContextHolder 的 CurrentUser 工具服务
```

## 15. 第十步：创建角色解析服务

文件：

```text
app/Services/TaskhubRoleService.php
```

完整内容：

```php
<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\TaskhubUserRole;

class TaskhubRoleService
{
    /**
     * @return list<string>
     */
    public function rolesFor(SsoUser $user): array
    {
        // SSO 只负责“这个人是谁”，TaskHub 业务角色由本系统数据库控制。
        // 使用数据库后，调整发布者/开发者/老板角色不需要修改环境变量或重新发布。
        return TaskhubUserRole::query()
            ->where('employee_no', $user->employeeNo())
            ->where('enabled', true)
            ->orderBy('role')
            ->pluck('role')
            ->filter(fn (mixed $role): bool => is_string($role) && $role !== '')
            ->values()
            ->all();
    }
}
```

为什么登录时查询角色：

- SSO 返回身份，TaskHub 查本系统角色。
- 登录后把角色写入 Session，页面跳转时不用每次都查数据库。
- 如果角色变更后需要立即生效，可以让用户重新登录，或后续做角色刷新机制。

本章不做角色管理页面，只提供数据库控制能力。

## 16. 第十一步：创建认证 Middleware

执行命令：

```bash
php artisan make:middleware EnsureSsoAuthenticated
```

文件：

```text
app/Http/Middleware/EnsureSsoAuthenticated.php
```

完整内容：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSsoAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has('sso_user')) {
            return $next($request);
        }

        $request->session()->put('url.intended', $request->fullUrl());

        return redirect()->route('sso.login');
    }
}
```

它做了三件事：

1. 检查 Session 中有没有 `sso_user`。
2. 没有登录时保存当前完整 URL。
3. 跳转到 SSO 登录入口。

Java 类比：

```text
Laravel Middleware
≈ Spring MVC HandlerInterceptor
≈ Servlet Filter
```

区别：

- Laravel Middleware 可以直接挂到路由组。
- 本章没有接 Laravel 默认 Auth Guard，因为 TaskHub 不创建本地 `users` 表。

## 17. 第十二步：创建 SSO Controller

执行命令：

```bash
php artisan make:controller SsoController
```

文件：

```text
app/Http/Controllers/SsoController.php
```

完整内容：

```php
<?php

namespace App\Http\Controllers;

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SsoController extends Controller
{
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
            'response_type' => 'token',
            'client_id' => $clientId,
            'redirect_uri' => route('sso.callback'),
            'scope' => config('sso.scope'),
        ], fn (mixed $value): bool => is_string($value) && $value !== ''));

        return redirect()->away($loginUrl.(str_contains($loginUrl, '?') ? '&' : '?').$query);
    }

    public function callback(): Response
    {
        // 公司 SSO 会把 access_token 作为 query string 回调到这个地址。
        // 这里仍返回 React 回调页，由前端统一处理登录中、失败提示和提交 /sso/session。
        return Inertia::render('Sso/Callback');
    }

    public function store(Request $request, SsoClient $ssoClient, TaskhubRoleService $roleService): JsonResponse
    {
        // 前端只提交 accessToken，不提交姓名、部门或角色。
        // 当前登录人的可信身份必须由后端拿 token 调公司 SSO 接口得到。
        $validated = $request->validate([
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

        // Laravel Session 保存的是已认证用户快照和 TaskHub 本地业务角色。
        // 角色来自 taskhub_user_role 表，变更角色不需要重新发布应用。
        $request->session()->put(CurrentUserService::SESSION_KEY, $user->toSessionPayload());
        $request->session()->put(CurrentUserService::ROLE_SESSION_KEY, $roles);

        // 登录成功后刷新 Session ID，降低会话固定攻击风险。
        $request->session()->regenerate();

        return response()->json([
            'redirectTo' => $request->session()->pull('url.intended', route('tasks.index')),
            'roles' => $roles,
        ]);
    }

    public function logout(Request $request): RedirectResponse
    {
        // 退出 TaskHub 时清理本系统保存的登录态和角色。
        // 公司 SSO 是否需要单点登出，等待总部协议确认后再补充。
        $request->session()->forget([
            CurrentUserService::SESSION_KEY,
            CurrentUserService::ROLE_SESSION_KEY,
        ]);

        // 退出后刷新 CSRF token，避免旧页面继续复用退出前的 token。
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
```

四个方法职责：

| 方法 | 路由 | 职责 |
|---|---|---|
| `redirect` | `GET /login` | 拼接公司 SSO 登录地址并跳转 |
| `callback` | `GET /sso/callback` | 返回 React 回调页 |
| `store` | `POST /sso/session` | 用 accessToken 获取当前登录人并建立 Session |
| `logout` | `POST /logout` | 清理 TaskHub 本地登录态 |

## 18. 第十三步：注册路由

文件：

```text
routes/web.php
```

完整内容：

```php
<?php

use App\Http\Controllers\SsoController;
use App\Http\Controllers\TaskController;
use App\Http\Middleware\EnsureSsoAuthenticated;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'appName' => config('app.name'),
    ]);
})->name('home');

Route::get('/login', [SsoController::class, 'redirect'])->name('sso.login');
Route::get(config('sso.callback_path', '/sso/callback'), [SsoController::class, 'callback'])->name('sso.callback');
Route::post('/sso/session', [SsoController::class, 'store'])->name('sso.session.store');
Route::post('/logout', [SsoController::class, 'logout'])->name('sso.logout');

Route::middleware(EnsureSsoAuthenticated::class)->group(function (): void {
    Route::get('/tasks', [TaskController::class, 'index'])->name('tasks.index');
});
```

为什么业务路由放进 Middleware 组：

- `/` 是公开首页。
- `/login` 和 `/sso/callback` 不能被登录拦截，否则登录流程会死循环。
- `/tasks` 是业务页面，需要登录后访问。

验证路由：

```bash
php artisan route:list
```

你应该看到：

```text
GET|HEAD  /
GET|HEAD  login
GET|HEAD  sso/callback
POST      sso/session
POST      logout
GET|HEAD  tasks
```

## 19. 第十四步：确保 Blade 提供 CSRF token

文件：

```text
resources/views/app.blade.php
```

完整内容：

```blade
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title inertia>{{ config('app.name', 'TaskHub') }}</title>
        @viteReactRefresh
        @vite('resources/js/app.tsx')
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
```

本章重点是这一行：

```blade
<meta name="csrf-token" content="{{ csrf_token() }}">
```

为什么需要：

- `/sso/session` 是 `POST` 请求。
- Laravel Web 路由默认启用 CSRF 校验。
- React 需要从这个 meta 中读取 token，并通过 `X-CSRF-TOKEN` 请求头提交。

如果没有这一行，前端 POST `/sso/session` 可能报：

```text
419 CSRF token mismatch
```

## 20. 第十五步：创建 React SSO 回调页

命令执行目录：项目根目录。

执行：

```bash
mkdir -p resources/js/Pages/Sso
```

创建文件：

```text
resources/js/Pages/Sso/Callback.tsx
```

完整内容：

```tsx
import { useEffect, useState } from 'react';

type CallbackState = 'processing' | 'failed';

function readAuthParams(): URLSearchParams {
    // 公司 SSO 已确认使用 query string 回调，例如：
    // /sso/callback?access_token=xxx
    // 因此这里读取 window.location.search，而不是 window.location.hash。
    return new URLSearchParams(window.location.search);
}

function csrfToken(): string {
    // /sso/session 是 Laravel web 路由，POST 请求需要 CSRF token。
    // token 来自 resources/views/app.blade.php 中的 csrf meta。
    return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

export default function SsoCallback() {
    const [callbackState, setCallbackState] = useState<CallbackState>('processing');
    const [message, setMessage] = useState('正在完成单点登录，请稍候。');

    useEffect(() => {
        // 登录收尾属于副作用，需要放在 useEffect 中，避免 React 重新渲染时重复提交。
        // 这个页面只负责把公司 SSO 回调带回来的 access_token 交给 Laravel。
        const params = readAuthParams();
        const accessToken = params.get('access_token');

        if (!accessToken) {
            // 没有 token 时不能继续创建本地 Session，必须让用户重新走登录流程。
            setCallbackState('failed');
            setMessage('SSO 回调缺少 access_token。');
            return;
        }

        // 前端只把 accessToken 交给 Laravel。
        // 当前用户身份、工号、角色都由后端调用公司接口和本地角色表确定。
        fetch('/sso/session', {
            method: 'POST',
            headers: {
                // 告诉 Laravel：前端希望后端返回 JSON，而不是重定向或 HTML 错误页。
                Accept: 'application/json',
                'Content-Type': 'application/json',
                // Laravel web 路由默认开启 CSRF 校验，POST 请求必须带这个头。
                'X-CSRF-TOKEN': csrfToken(),
            },
            body: JSON.stringify({
                accessToken,
            }),
        })
            .then(async (response) => {
                const payload = (await response.json()) as { redirectTo?: string; message?: string };

                if (!response.ok) {
                    throw new Error(payload.message ?? 'SSO 登录失败。');
                }

                // 后端建立 Session 成功后返回 redirectTo。
                // replace 会替换当前回调页历史记录，避免浏览器后退回到带 token 的 URL。
                window.location.replace(payload.redirectTo ?? '/tasks');
            })
            .catch((error: unknown) => {
                // 失败时停留在回调页，给用户一个明确的重新登录入口。
                setCallbackState('failed');
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
                            {callbackState === 'processing' ? '正在建立本地会话' : '登录未完成'}
                        </p>
                    </div>
                </div>

                <p className="text-sm leading-6 text-[#6e6e80]">{message}</p>

                {callbackState === 'failed' ? (
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

为什么这个页面不是业务页面：

- 它只处理登录回调。
- 不展示任务数据。
- 成功后立即跳转。
- 失败时只显示错误和重新登录入口。

映射关系：

```text
Inertia::render('Sso/Callback')
↓
resources/js/Pages/Sso/Callback.tsx
```

这是 Inertia 的页面命名约定。

## 21. 第十六步：共享当前登录人给 Inertia 页面

文件：

```text
app/Http/Middleware/HandleInertiaRequests.php
```

完整内容：

```php
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'app' => [
                'name' => config('app.name'),
            ],
            'auth' => [
                'user' => $request->session()->get('sso_user'),
                'roles' => $request->session()->get('taskhub_roles', []),
            ],
        ];
    }
}
```

为什么共享 `auth`：

- 后续 React 页面可以通过 Inertia props 获取当前用户和角色。
- 不需要每个 Controller 都重复传 `auth`。
- 类似 Spring MVC 中通过 `@ControllerAdvice` 或全局 Model 属性给所有页面共享数据。

注意：

- 当前只是共享 Session 中已有信息。
- 权限判断仍然应该在后端做，不能只靠前端隐藏按钮。

## 22. 第十七步：确认 Middleware 已注册到 web 栈

文件：

```text
bootstrap/app.php
```

关键内容：

```php
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
```

为什么注册到 `web`：

- Inertia 页面走 Laravel Web 路由。
- Web 路由有 Session、CSRF、Cookie。
- SSO 登录态依赖 Session，因此不能放到纯 API 栈。

## 23. 第十八步：补充测试

文件：

```text
tests/Feature/ApplicationShellTest.php
```

本章至少需要下面这些测试：

```php
<?php

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoUser;
use App\Models\Task;
use App\Models\TaskhubUserRole;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;

test('protected task pages redirect to sso login when session is missing', function (): void {
    $this->get('/tasks')->assertRedirect('/login');
});

test('the sso callback page responds successfully', function (): void {
    $this->withoutVite();

    $this->get('/sso/callback')->assertOk();
});

test('the sso session endpoint accepts access token without state', function (): void {
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        public function fetchCurrentUser(string $accessToken): SsoUser
        {
            expect($accessToken)->toBe('token-123');

            return new SsoUser(
                employeeNo: 'E10001',
                displayName: '张三',
                departmentId: 'DEV01',
                departmentName: '开发一部',
            );
        }
    });

    $this->app->instance(TaskhubRoleService::class, new class extends TaskhubRoleService
    {
        public function rolesFor(SsoUser $user): array
        {
            expect($user->employeeNo())->toBe('E10001');

            return ['DEVELOPER'];
        }
    });

    $this->postJson('/sso/session', [
        'accessToken' => 'token-123',
    ])
        ->assertOk()
        ->assertJson([
            'redirectTo' => route('tasks.index'),
            'roles' => ['DEVELOPER'],
        ]);

    expect(session(CurrentUserService::SESSION_KEY)['employeeNo'])->toBe('E10001')
        ->and(session(CurrentUserService::ROLE_SESSION_KEY))->toBe(['DEVELOPER']);
});

test('task model maps to the existing task table', function (): void {
    expect((new Task)->getTable())->toBe('task');
});

test('taskhub user role model maps to the existing role table', function (): void {
    expect((new TaskhubUserRole)->getTable())->toBe('taskhub_user_role');
});
```

为什么测试里替换 `SsoClient`：

- 本地和 CI 不能调用公司真实 SSO。
- 测试目标是验证 TaskHub 自己的流程，不是测试总部接口。
- 使用 Laravel 容器替换依赖，类似 Spring Boot 测试中使用 `@MockBean`。

本章不做 Mock 登录页面。测试中的替换只存在于测试运行时，不进入生产逻辑。

## 24. 第十九步：完整验证顺序

命令执行目录：项目根目录。

先检查 PHP 代码格式：

```bash
vendor/bin/pint --dirty
```

检查路由：

```bash
php artisan route:list
```

确认有：

```text
GET|HEAD  login
GET|HEAD  sso/callback
POST      sso/session
POST      logout
GET|HEAD  tasks
```

检查 TypeScript：

```bash
npm run typecheck
```

生产构建：

```bash
npm run build
```

运行测试：

```bash
php artisan test
```

启动开发服务器：

```bash
composer run dev
```

浏览器访问：

```text
http://127.0.0.1:8000/sso/callback
```

预期看到：

```text
SSO 回调缺少 access_token。
```

这说明回调页已经加载，只是当前 URL 没有公司 SSO 带回的 token。

再访问：

```text
http://127.0.0.1:8000/sso/callback?access_token=test-token
```

如果 `.env` 没有配置真实 `SSO_BASE_URL` 或 `SSO_USERINFO_PATH`，页面会显示后端返回的 SSO 配置错误。这是正常的，说明前端已经把 token 提交给了 `/sso/session`。

访问：

```text
http://127.0.0.1:8000/tasks
```

未登录时应该跳转：

```text
/login
```

如果 `SSO_LOGIN_URL` 或 `SSO_CLIENT_ID` 未配置，会看到配置错误。这是正常的，说明业务路由已经被 SSO Middleware 保护。

## 25. 常见错误与处理

| 错误 | 常见原因 | 处理 |
|---|---|---|
| 回调页提示缺少 `access_token` | 公司 SSO 回调地址没有带 `?access_token=...`，或参数名不是 `access_token` | 先确认总部回调参数名，再改 `Callback.tsx` |
| `SSO login URL is not configured` | `.env` 未配置 `SSO_LOGIN_URL` | 按公司协议填写登录地址后执行 `php artisan config:clear` |
| `SSO client ID is not configured` | `.env` 未配置 `SSO_CLIENT_ID` | 填写公司分配的 client id |
| `SSO base URL is not configured` | `.env` 未配置 `SSO_BASE_URL` | 填写公司当前登录人接口基础地址 |
| `SSO user info path is not configured` | `.env` 未配置 `SSO_USERINFO_PATH` | 填写 accessToken 换当前登录人的接口路径 |
| `419 CSRF token mismatch` | Blade 缺少 CSRF meta，或 fetch 未带 `X-CSRF-TOKEN` | 检查 `app.blade.php` 和 `Callback.tsx` |
| 登录后没有角色 | `taskhub_user_role` 没有该工号的启用角色 | 插入或启用对应角色记录 |
| 访问 `/tasks` 无限跳 `/login` | Session 没写入或浏览器 Cookie 被禁用 | 检查 `/sso/session` 响应、`SESSION_DRIVER=file`、浏览器 Cookie |
| 修改 `.env` 后仍报旧配置 | Laravel 配置缓存未清理 | 执行 `php artisan config:clear && php artisan cache:clear` |

## 26. 关键设计原因

### 26.1 为什么不用 Laravel 默认 users 表

TaskHub 不维护完整本地人员资料。

原因：

- 公司 SSO 和外部人员接口才是身份来源。
- TaskHub 只保存业务数据中的工号和历史快照。
- 本地 `users` 表会制造双重身份来源。

### 26.2 为什么不用 Mock 登录

本项目从第一阶段开始接真实 SSO。

Mock 登录会让开发更快，但风险是：

- 代码容易围绕假登录设计。
- 后续切真实协议时改动更大。
- 学习者会误解真实认证流程。

本章只在测试中替换 `SsoClient`，不提供生产可用的 Mock 登录入口。

### 26.3 为什么角色放数据库

SSO 负责身份认证，TaskHub 自己负责业务角色。

角色放数据库的好处：

- 修改角色不需要重新发布。
- 可以按工号启用或禁用角色。
- 后续可以自然扩展角色管理页面。

### 26.4 为什么当前不校验 state

标准 OAuth/OIDC 推荐 `state`，但公司 SSO 当前没有这个能力。

因此本章不生成、不传递、不校验 `state`。

当前安全边界是：

```text
只接受 accessToken
↓
后端用 accessToken 调公司接口
↓
只信任公司接口返回的 employeeNo
↓
角色从 TaskHub 数据库查询
```

如果后续公司 SSO 支持 `state`，再补回该能力。

## 27. 本章检查清单

完成后逐项检查：

- `.env.example` 有 SSO 配置字段。
- 本地 `.env` 已补充 SSO 配置字段。
- `config/sso.php` 存在。
- `config/taskhub.php` 存在。
- `SsoException` 存在。
- `SsoUser` 存在。
- `SsoClient` 存在，并有 `fetchCurrentUser()`。
- `CurrentUserService` 存在。
- `TaskhubRoleService` 存在。
- `EnsureSsoAuthenticated` 存在。
- `SsoController` 有 `redirect`、`callback`、`store`、`logout`。
- `routes/web.php` 有 `/login`、`/sso/callback`、`/sso/session`、`/logout`。
- `/tasks` 已挂 `EnsureSsoAuthenticated`。
- `app.blade.php` 有 CSRF meta。
- `Callback.tsx` 读取 `window.location.search`。
- `Callback.tsx` 不读取 `window.location.hash`。
- 代码没有强制要求 `state`。
- `taskhub_user_role` 表存在。
- `php artisan test` 通过。
- `npm run typecheck` 通过。
- `npm run build` 通过。

## 28. 本章总结

本章完成的是 TaskHub 的认证基础设施：

- 浏览器未登录时跳转公司 SSO。
- 公司 SSO 回调 TaskHub。
- React 回调页读取 `?access_token=...`。
- Laravel 后端用 token 获取当前登录人。
- TaskHub 用工号查询本地业务角色。
- 用户和角色写入 Laravel Session。
- 业务页面通过 Middleware 保护。

核心原则：

```text
SSO 负责认证身份；
TaskHub 数据库负责业务角色；
Laravel Session 负责浏览器登录态；
Middleware 负责保护业务路由；
React 回调页负责浏览器侧登录收尾。
```

## 29. 下一章预告

SSO 骨架完成后，下一步可以正式进入业务功能开发。

建议从任务大厅开始：

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

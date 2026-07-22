<?php

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use App\Models\Task;
use App\Models\TaskhubUserRole;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

test('the inertia application shell responds successfully', function (): void {
    // Feature Test 不需要真实加载 Vite 资源；withoutVite 可以避免测试依赖前端构建产物。
    $this->withoutVite();

    // 首页是最小 Inertia 应用壳，能返回 200 说明 Blade + Inertia 基础链路可用。
    $this->get('/')->assertOk();
});

test('protected task pages redirect to sso login when session is missing', function (): void {
    // /tasks 是受保护业务页面；没有 sso_user Session 时必须进入 SSO 登录流程。
    $this->get('/tasks')->assertRedirect('/login');
});

test('the sso callback page responds successfully', function (): void {
    $this->withoutVite();

    // SSO 回调页本身不验证 token，只负责把 query string 中的 access_token 交给后端。
    $this->get('/sso/callback')->assertOk();
});

test('authenticated users can view the task hall with filters', function (): void {
    $this->withoutVite();
    // 测试数据库不执行正式 schema.sql，因此这里为本测试创建最小可用表结构。
    createTaskHallTables();

    // 插入一个已截止但仍有 ACTIVE 投标的 OPEN 任务，用来验证“待选标”派生状态。
    DB::table('task')->insert([
        'id' => 10001,
        'title' => '用户登录页 UI 重构',
        'description' => '按照原型重构登录页。',
        'payment_account_id' => 'PAY001',
        'payment_account_snapshot' => json_encode(['accountName' => '产品研发预算'], JSON_THROW_ON_ERROR),
        'budget' => 800,
        'expected_delivery' => '2026-08-01',
        'bidding_deadline' => now()->subDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        'created_by_snapshot' => json_encode([
            'displayName' => '陈PM',
            'departmentName' => '产品研发部',
        ], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // ACTIVE 投标数量会通过 withCount 统计出来，并影响 PENDING_SELECTION 展示。
    DB::table('bid')->insert([
        'id' => 20001,
        'task_id' => 10001,
        'amount' => 780,
        'delivery_date' => '2026-07-30',
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10001:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // 通过 Session 模拟已经完成 SSO 登录的用户，不真实跳转公司 SSO。
    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
    ])
        // Inertia 请求需要带这两个头，Laravel 才会返回 JSON page payload。
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks?status=PENDING_SELECTION&complexity=MEDIUM&keyword=%E7%99%BB%E5%BD%95')
        ->assertOk()
        // 验证后端返回的 Inertia 组件名、筛选回显和派生状态都正确。
        ->assertJsonPath('component', 'Tasks/Index')
        ->assertJsonPath('props.filters.status', 'PENDING_SELECTION')
        ->assertJsonPath('props.filters.complexity', 'MEDIUM')
        ->assertJsonPath('props.filters.keyword', '登录')
        ->assertJsonPath('props.tasks.data.0.displayStatus', 'PENDING_SELECTION')
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 1);
});

test('authenticated users can publish a bidding task with attachment ids', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
    ])
        ->post('/tasks', [
            'title' => '报表导出优化',
            'description' => '优化现有报表导出速度，并补充异常提示。',
            'budget' => '3000.00',
            'expectedDelivery' => now()->addDays(7)->toDateString(),
            'biddingDeadline' => now()->addDay()->format('Y-m-d\TH:i'),
            'complexity' => 'MEDIUM',
            'paymentAccountId' => 'PAY001',
            'paymentAccountName' => '开发一部创新预算',
            'attachmentIds' => "ATT001\nATT002,ATT001",
        ])
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHas('success', '任务已发布。');

    $task = DB::table('task')->where('title', '报表导出优化')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('OPEN')
        ->and($task->assignment_type)->toBe('BIDDING')
        ->and($task->created_by)->toBe('E10002');

    expect(DB::table('attachment_ref')->where('owner_id', $task->id)->pluck('attachment_id')->sort()->values()->all())
        ->toBe(['ATT001', 'ATT002']);

    expect(DB::table('task_event')->where('task_id', $task->id)->orderBy('id')->pluck('event_type')->all())
        ->toBe(['TASK_CREATED', 'TASK_PUBLISHED']);
});

test('the sso session endpoint accepts access token without state', function (): void {
    // 用容器替换 SsoClient，避免测试真实访问公司 SSO。
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        public function fetchCurrentUser(string $accessToken): SsoUser
        {
            // 确认前端提交的 accessToken 被原样传给后端 SSO 客户端。
            expect($accessToken)->toBe('token-123');

            return new SsoUser(
                employeeNo: 'E10001',
                displayName: '张三',
                departmentId: 'DEV01',
                departmentName: '开发一部',
            );
        }
    });

    // 用容器替换角色服务，验证角色来源是后端服务，不是前端传入。
    $this->app->instance(TaskhubRoleService::class, new class extends TaskhubRoleService
    {
        public function rolesFor(SsoUser $user): array
        {
            expect($user->employeeNo())->toBe('E10001');

            return ['TOP'];
        }
    });

    $this->postJson('/sso/session', [
        // 公司 SSO 回调给前端 token，前端再提交给 Laravel 建立本地 Session。
        'accessToken' => 'token-123',
    ])
        ->assertOk()
        ->assertJson([
            'redirectTo' => route('tasks.index'),
            'roles' => ['TOP'],
        ]);

    // 登录成功后，用户快照和 TaskHub 角色都应写入 Session。
    expect(session(CurrentUserService::SESSION_KEY)['employeeNo'])->toBe('E10001')
        ->and(session(CurrentUserService::ROLE_SESSION_KEY))->toBe(['TOP']);
});

test('logout clears sso session and redirects home', function (): void {
    // 未配置总部退出地址时，只退出 TaskHub 本地 Session，然后回到首页。
    config(['sso.logout_url' => null]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '张三',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
        'taskhub.session_marker' => 'old-session',
    ])
        ->post('/logout')
        ->assertRedirect(route('home'))
        // invalidate() 后旧 Session 数据都不应继续存在。
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing('taskhub.session_marker');
});

test('logout redirects to sso logout url when configured', function (): void {
    // 配置总部退出地址后，本地 Session 清理完成，再跳转到外部 SSO 退出页。
    config(['sso.logout_url' => 'https://sso.example.test/logout']);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '张三',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
        'taskhub.session_marker' => 'old-session',
    ])
        ->post('/logout')
        ->assertRedirect('https://sso.example.test/logout')
        // 即使跳转到外部退出地址，本地 Session 也必须已经清理。
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing('taskhub.session_marker');
});

test('sso user info path must not be a full url', function (): void {
    // userinfo_path 只允许写 path，避免和 base_url 拼接时产生错误地址。
    config([
        'sso.base_url' => 'https://sso.example.test',
        'sso.client_id' => 'ClientID',
        'sso.client_secret' => 'secret',
        'sso.userinfo_path' => 'https://sso.example.test/api/current-user',
    ]);

    expect(fn () => app(SsoClient::class)->fetchCurrentUser('token-123'))
        ->toThrow(SsoException::class, 'SSO user info path must be a path, not a full URL.');
});

test('sso client posts json payload to user info endpoint', function (): void {
    // 这里验证公司推荐调用方式：POST JSON，body 包含 clientId、secret、accessToken。
    config([
        'sso.base_url' => 'https://sso.example.test',
        'sso.client_id' => 'ClientID',
        'sso.client_secret' => 'secret',
        'sso.userinfo_path' => '/api/current-user',
        'sso.timeout' => 3,
        'sso.verify_ssl' => false,
    ]);

    // Http::fake 拦截 Laravel HTTP Client 请求，不会真实访问网络。
    Http::fake([
        'https://sso.example.test/api/current-user' => Http::response([
            'id' => 'response-001',
            'user' => [
                'employeeNo' => 'E10001',
                'displayName' => '张三',
            ],
        ]),
    ]);

    $user = app(SsoClient::class)->fetchCurrentUser('token-123');

    expect($user->employeeNo())->toBe('E10001');

    // 断言请求方法、地址和 JSON body，防止后续改动破坏总部接口协议。
    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://sso.example.test/api/current-user'
        && $request->data()['clientId'] === 'ClientID'
        && $request->data()['secret'] === 'secret'
        && $request->data()['accessToken'] === 'token-123');
});

test('sso user parses nested user payload', function (): void {
    // 公司接口第一层包含 id 和 user，人员字段在 user 下；这里固定解析规则。
    $user = SsoUser::fromPayload([
        'id' => 'response-001',
        'user' => [
            'employeeNo' => 'E10001',
            'displayName' => '张三',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
        ],
    ]);

    expect($user->employeeNo())->toBe('E10001')
        ->and($user->displayName())->toBe('张三')
        ->and($user->departmentId())->toBe('DEV01')
        ->and($user->departmentName())->toBe('开发一部')
        ->and($user->raw())->toHaveKey('user');
});

test('task model maps to the existing task table', function (): void {
    // 防止 Model 被误改成 Laravel 默认复数表名 tasks。
    expect((new Task)->getTable())->toBe('task');
});

test('taskhub user role model maps to the existing role table', function (): void {
    // 角色表是 TaskHub 自己控制业务角色的入口，不依赖环境变量硬编码。
    expect((new TaskhubUserRole)->getTable())->toBe('taskhub_user_role');
});

function createTaskHallTables(): void
{
    // SQLite 内存库在同一测试进程内可能复用连接；创建前先清理，保证测试互不影响。
    Schema::dropIfExists('task_event');
    Schema::dropIfExists('attachment_ref');
    Schema::dropIfExists('bid');
    Schema::dropIfExists('task');

    // 这里只创建 TaskController@index 需要的最小字段，不复制完整 schema.sql。
    // 完整数据库设计仍以 database/schema.sql 为准。
    Schema::create('task', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('title', 200);
        $table->longText('description')->nullable();
        $table->string('payment_account_id', 64);
        $table->json('payment_account_snapshot')->nullable();
        $table->decimal('budget', 18, 2);
        $table->decimal('final_amount', 18, 2)->nullable();
        $table->date('expected_delivery');
        $table->date('final_delivery')->nullable();
        $table->timestamp('bidding_deadline')->nullable();
        $table->string('status', 20);
        $table->string('assignment_type', 20);
        $table->string('complexity', 20);
        $table->string('created_by', 32);
        $table->json('created_by_snapshot')->nullable();
        $table->unsignedBigInteger('assigned_bid_id')->nullable();
        $table->string('primary_assignee_id', 32)->nullable();
        $table->bigInteger('version')->default(0);
        $table->timestamp('completed_at')->nullable();
        $table->timestamps();
        $table->string('updated_by', 32)->nullable();
    });

    // bid 表只需要支持 active_bid_count 和 PENDING_SELECTION 的 whereHas 查询。
    Schema::create('bid', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->decimal('amount', 18, 2);
        $table->date('delivery_date');
        $table->text('proposal')->nullable();
        $table->string('status', 20);
        $table->integer('revision_no')->default(1);
        $table->string('active_key', 160)->nullable();
        $table->timestamp('withdrawn_at')->nullable();
        $table->timestamps();
    });

    Schema::create('attachment_ref', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('owner_type', 30);
        $table->unsignedBigInteger('owner_id');
        $table->string('attachment_id', 128);
        $table->string('uploaded_by', 32);
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('task_event', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->string('event_type', 40);
        $table->string('operator_id', 32)->nullable();
        $table->string('from_status', 20)->nullable();
        $table->string('to_status', 20)->nullable();
        $table->string('related_type', 30)->nullable();
        $table->unsignedBigInteger('related_id')->nullable();
        $table->json('event_data')->nullable();
        $table->string('remark', 500)->nullable();
        $table->timestamp('created_at')->nullable();
    });
}

function inertiaVersionForTest(): string
{
    $manifest = public_path('build/manifest.json');

    // 如果本地存在 build manifest，就用 Inertia 的真实版本算法；否则返回空字符串配合 withoutVite。
    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

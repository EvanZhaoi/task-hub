<?php

use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use App\Models\Task;
use App\Models\TaskhubUserRole;
use App\Services\CurrentUserService;
use App\Services\SnowflakeId;
use App\Services\TaskhubRoleService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
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

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回测试用付款账号列表。
         *
         * 这里替代真实外部付款账号接口，让任务大厅测试只关注页面数据组装。
         */
        public function fetchAll(): array
        {
            // 任务大厅打开时会加载付款账号列表给发布任务 Select 使用。
            return [
                new PaymentAccount(
                    accountId: 'PAY001',
                    accountName: '产品研发预算',
                    departmentId: 'DEV01',
                    departmentName: '产品研发部',
                ),
            ];
        }
    });

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
        ->assertJsonPath('props.paymentAccountOptions.0.value', 'PAY001')
        ->assertJsonPath('props.tasks.data.0.displayStatus', 'PENDING_SELECTION')
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 1);
});

test('authenticated users can publish a bidding task with attachment ids', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回测试用付款账号详情。
         *
         * 发布任务时后端会按账号 ID 重新查询外部账号信息，本方法用来验证这个调用发生了。
         */
        public function fetchById(string $accountId): PaymentAccount
        {
            expect($accountId)->toBe('PAY001');

            return new PaymentAccount(
                accountId: 'PAY001',
                accountName: '开发一部创新预算',
                departmentId: 'DEV01',
                departmentName: '开发一部',
            );
        }
    });

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
            'attachmentIds' => "ATT001\nATT002,ATT001",
        ])
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHas('success', '任务已发布。');

    $task = DB::table('task')->where('title', '报表导出优化')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('OPEN')
        ->and($task->assignment_type)->toBe('BIDDING')
        ->and($task->created_by)->toBe('E10002');

    expect(json_decode($task->payment_account_snapshot, true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'accountId' => 'PAY001',
            'accountName' => '开发一部创新预算',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
        ]);

    expect(DB::table('attachment_ref')->where('owner_id', $task->id)->pluck('attachment_id')->sort()->values()->all())
        ->toBe(['ATT001', 'ATT002']);

    expect(DB::table('task_event')->where('task_id', $task->id)->orderBy('id')->pluck('event_type')->all())
        ->toBe(['TASK_CREATED', 'TASK_PUBLISHED']);
});

test('payment account client fetches account snapshot from external service', function (): void {
    // 付款账号属于外部主数据；TaskHub 只根据账号 ID 调接口获取展示快照。
    // 测试中使用 Http::fake 拦截请求，既能验证请求地址，也不会真实访问公司接口。
    config([
        'payment_account.base_url' => 'https://payment.example.test',
        'payment_account.detail_path' => '/accounts/{accountId}',
        'payment_account.method' => 'GET',
        'payment_account.timeout' => 3,
        'payment_account.verify_ssl' => false,
    ]);

    Http::fake([
        'https://payment.example.test/accounts/PAY001' => Http::response([
            'accountId' => 'PAY001',
            'accountName' => '开发一部创新预算',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
        ]),
    ]);

    $paymentAccount = app(PaymentAccountClient::class)->fetchById('PAY001');

    expect($paymentAccount->toSnapshot())->toMatchArray([
        'accountId' => 'PAY001',
        'accountName' => '开发一部创新预算',
        'departmentId' => 'DEV01',
        'departmentName' => '开发一部',
    ]);

    // 断言账号 ID 由后端放进外部接口 path，防止以后又退回到前端提交账号名称的方案。
    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && $request->url() === 'https://payment.example.test/accounts/PAY001');
});

test('external directory cache refresh keeps previous payment accounts when response is empty', function (): void {
    config([
        'payment_account.base_url' => 'https://payment.example.test',
        'payment_account.list_path' => '/accounts',
        'payment_account.detail_path' => null,
        'payment_account.method' => 'GET',
        'payment_account.timeout' => 3,
        'payment_account.verify_ssl' => false,
        'payment_account.cache_store' => 'array',
        'payment_account.cache_key' => 'test:payment_accounts',
        'payment_account.cache_ttl' => 86400,
    ]);

    Cache::store('array')->forget('test:payment_accounts');

    Http::fake([
        'https://payment.example.test/accounts' => Http::sequence()
            ->push([
                'data' => [
                    [
                        'accountId' => 'PAY001',
                        'accountName' => '开发一部创新预算',
                    ],
                ],
            ])
            ->push([
                'data' => [],
            ]),
    ]);

    expect(app(PaymentAccountClient::class)->refreshCache())->toBe(1)
        ->and(app(PaymentAccountClient::class)->fetchAll()[0]->accountId())->toBe('PAY001');

    // 外部接口返回空列表时，refreshCache 返回 0，并保留上一次成功同步的缓存。
    expect(app(PaymentAccountClient::class)->refreshCache())->toBe(0)
        ->and(app(PaymentAccountClient::class)->fetchAll()[0]->accountId())->toBe('PAY001');
});

test('external directory cache refresh keeps previous personnel users when response is empty', function (): void {
    config([
        'personnel.base_url' => 'https://personnel.example.test',
        'personnel.list_path' => '/users',
        'personnel.method' => 'GET',
        'personnel.timeout' => 3,
        'personnel.verify_ssl' => false,
        'personnel.cache_store' => 'array',
        'personnel.cache_key' => 'test:personnel_users',
        'personnel.cache_ttl' => 86400,
    ]);

    Cache::store('array')->forget('test:personnel_users');

    Http::fake([
        'https://personnel.example.test/users' => Http::sequence()
            ->push([
                'users' => [
                    [
                        'employeeNo' => '10001',
                        'displayName' => '张三',
                        'departmentId' => 'DEV01',
                        'departmentName' => '开发一部',
                    ],
                ],
            ])
            ->push([
                'users' => [],
            ]),
    ]);

    expect(app(PersonnelClient::class)->refreshCache())->toBe(1)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三');

    // 外部接口返回空列表时，refreshCache 返回 0，并保留上一次成功同步的缓存。
    expect(app(PersonnelClient::class)->refreshCache())->toBe(0)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三');
});

test('the sso session endpoint accepts access token without state', function (): void {
    // 用容器替换 SsoClient，避免测试真实访问公司 SSO。
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        /**
         * 返回测试用 SSO 登录人。
         *
         * 该方法替代真实总部接口，验证 accessToken 会从前端提交到后端 SSO 客户端。
         */
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

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 模拟本据点人员列表未找到当前人。
         *
         * 返回 null 表示当前登录人不是本据点人员，Session 只保存总部 SSO 用户信息。
         */
        public function findByEmployeeNo(string $employeeNo): ?PersonnelUser
        {
            // 本测试只验证 SSO 建 Session 主流程，人员列表返回 null 表示不属于本据点，继续使用总部信息。
            expect($employeeNo)->toBe('E10001');

            return null;
        }
    });

    // 用容器替换角色服务，验证角色来源是后端服务，不是前端传入。
    $this->app->instance(TaskhubRoleService::class, new class extends TaskhubRoleService
    {
        /**
         * 返回测试用 TaskHub 角色。
         *
         * 角色由后端服务根据数据库或测试替身决定，不能由前端请求直接指定。
         */
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

test('sso session prefers local personnel list when user belongs to current site', function (): void {
    // 总部 SSO 返回的信息可能不完整，这里故意只返回姓名，不返回部门。
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        /**
         * 返回总部 SSO 登录人测试数据。
         *
         * 这里故意让总部数据缺少部门，用于验证本据点人员信息会作为 siteUser 额外补充。
         */
        public function fetchCurrentUser(string $accessToken): SsoUser
        {
            expect($accessToken)->toBe('token-456');

            return new SsoUser(
                employeeNo: '00010001',
                displayName: '总部张三',
            );
        }
    });

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回本据点人员列表中的当前用户。
         *
         * 该方法模拟本据点接口命中人员后，把更准确的人员信息写入 Session 的 siteUser 字段。
         */
        public function findByEmployeeNo(string $employeeNo): ?PersonnelUser
        {
            // 本据点工号不带前导 0；PersonnelClient 会负责按本地规则匹配和返回准确人员信息。
            expect($employeeNo)->toBe('00010001');

            return new PersonnelUser(
                employeeNo: '10001',
                displayName: '张三',
                departmentId: 'DEV01',
                departmentName: '开发一部',
            );
        }
    });

    $this->app->instance(TaskhubRoleService::class, new class extends TaskhubRoleService
    {
        /**
         * 返回当前登录人的 TaskHub 角色。
         *
         * 测试中仍使用总部 SSO 用户对象查询角色，证明 siteUser 只是补充展示信息。
         */
        public function rolesFor(SsoUser $user): array
        {
            // 角色查询仍使用总部 SSO 原始用户对象；本据点人员信息只作为 Session 中的 siteUser 附加字段。
            expect($user->employeeNo())->toBe('00010001');

            return ['TOP'];
        }
    });

    $this->postJson('/sso/session', [
        'accessToken' => 'token-456',
    ])->assertOk();

    expect(session(CurrentUserService::SESSION_KEY))->toMatchArray([
        // 顶层字段保留总部 SSO 当前登录人信息，不被本据点人员列表覆盖。
        'employeeNo' => '00010001',
        'displayName' => '总部张三',
        // 本据点更准确的信息放在额外数组字段中，后续页面和选择器可以按需使用。
        'siteUser' => [
            'employeeNo' => '10001',
            'displayName' => '张三',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
            'raw' => [],
        ],
    ]);
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

test('snowflake id generates increasing ids in the same application process', function (): void {
    $ids = new SnowflakeId;

    $first = $ids->next();
    $second = $ids->next();
    $third = $ids->next();

    expect($second)->toBeGreaterThan($first)
        ->and($third)->toBeGreaterThan($second);
});

/**
 * 为任务大厅相关测试创建最小 SQLite 表结构。
 *
 * 测试环境不执行正式 MySQL schema.sql，因此只创建 Controller 当前查询和写入会用到的字段。
 */
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

/**
 * 获取测试请求使用的 Inertia 资源版本。
 *
 * 如果本地存在 Vite build manifest，就返回真实版本；否则返回空字符串配合 withoutVite 使用。
 */
function inertiaVersionForTest(): string
{
    $manifest = public_path('build/manifest.json');

    // 如果本地存在 build manifest，就用 Inertia 的真实版本算法；否则返回空字符串配合 withoutVite。
    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

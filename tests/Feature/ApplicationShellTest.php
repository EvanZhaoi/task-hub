<?php

use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoToken;
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

    // 授权码模式下缺少 code 时，回调页直接展示错误提示，方便开发阶段排查 SSO 参数问题。
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
                    wbaAccountCode: 'PAY001',
                    wbaAccountName: '产品研发预算',
                    deptName: '产品研发部',
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
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
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
         * 返回测试用付款账号。
         *
         * 发布任务时后端会按账号 ID 从付款账号列表中筛选账号，本方法用来隔离外部接口依赖。
         */
        public function fetchById(string $accountId): PaymentAccount
        {
            expect($accountId)->toBe('PAY001');

            return new PaymentAccount(
                wbaAccountCode: 'PAY001',
                wbaAccountName: '开发一部创新预算',
                deptName: '开发一部',
            );
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
            'departmentId' => 'DEV01',
            'departmentName' => '开发一部',
            'avatarId' => 'avatar-002',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
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
            'departmentName' => '开发一部',
        ]);

    expect(json_decode($task->created_by_snapshot, true, flags: JSON_THROW_ON_ERROR))
        ->toMatchArray([
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
            'departmentName' => '开发一部',
            'avatarId' => 'avatar-002',
        ]);

    expect(DB::table('attachment_ref')->where('owner_id', $task->id)->pluck('attachment_id')->sort()->values()->all())
        ->toBe(['ATT001', 'ATT002']);

    expect(DB::table('task_event')->where('task_id', $task->id)->orderBy('id')->pluck('event_type')->all())
        ->toBe(['TASK_CREATED', 'TASK_PUBLISHED']);
});

test('payment account client finds account snapshot from external account list', function (): void {
    // 付款账号属于外部主数据；当前公司接口只提供全量列表，不提供单账号详情。
    // 测试中使用 Http::fake 拦截列表请求，既能验证请求地址，也不会真实访问公司接口。
    config([
        'payment_account.base_url' => 'https://payment.example.test',
        'payment_account.list_path' => '/accounts',
        'payment_account.method' => 'GET',
        'payment_account.timeout' => 3,
        'payment_account.verify_ssl' => false,
    ]);

    Http::fake([
        'https://payment.example.test/accounts' => Http::response([
            'data' => [
                [
                    'deptName' => '开发一部',
                    'wbaAccountCode' => 'PAY001',
                    'wbaAccountName' => '开发一部创新预算',
                    'wbaType' => 'BUDGET',
                ],
                [
                    'deptName' => '测试部',
                    'wbaAccountCode' => 'PAY002',
                    'wbaAccountName' => '测试预算',
                    'wbaType' => 'BUDGET',
                ],
            ],
            'msg' => '',
            'timestamp' => 0,
            'total' => 2,
        ]),
    ]);

    $paymentAccount = app(PaymentAccountClient::class)->fetchById('PAY001');

    expect($paymentAccount->toSnapshot())->toMatchArray([
        'accountId' => 'PAY001',
        'accountName' => '开发一部创新预算',
        'departmentName' => '开发一部',
    ])
        ->and($paymentAccount->wbaAccountCode())->toBe('PAY001')
        ->and($paymentAccount->wbaAccountName())->toBe('开发一部创新预算')
        ->and($paymentAccount->deptName())->toBe('开发一部')
        ->and($paymentAccount->wbaType())->toBe('BUDGET');

    // 断言后端只调用全量列表接口，然后在本地按 accountId 筛选。
    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && $request->url() === 'https://payment.example.test/accounts');
});

test('external directory cache refresh keeps previous payment accounts when response is empty', function (): void {
    config([
        'payment_account.base_url' => 'https://payment.example.test',
        'payment_account.list_path' => '/accounts',
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
                        'deptName' => '开发一部',
                        'wbaAccountCode' => 'PAY001',
                        'wbaAccountName' => '开发一部创新预算',
                        'wbaType' => 'BUDGET',
                    ],
                ],
                'msg' => '',
                'timestamp' => 0,
                'total' => 1,
            ])
            ->push([
                'data' => [],
                'msg' => '',
                'timestamp' => 0,
                'total' => 0,
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
                'data' => [
                    [
                        'copSort' => 0,
                        'department' => '开发一部',
                        'deptInfoList' => [
                            [
                                'copName' => '',
                                'copSort' => 5,
                                'obiCode' => 'DEV05',
                                'obiName' => '开发五部',
                                'obiUuid' => 'DEPT-UUID-05',
                            ],
                            [
                                'copName' => '',
                                'copSort' => 1,
                                'obiCode' => 'DEV01',
                                'obiName' => '开发一部',
                                'obiUuid' => 'DEPT-UUID-01',
                            ],
                        ],
                        'eibEmail' => 'zhangsan@example.test',
                        'eibName' => '张三',
                        'eibNameCn' => '张三',
                        'eibNumCn' => '10001',
                        'eibUserName' => 'zhangsan',
                        'id' => 'person-001',
                        'obiUuid' => 'DEPT-UUID-01',
                    ],
                ],
                'msg' => '',
                'timestamp' => 0,
                'total' => 1,
            ])
            ->push([
                'data' => [],
                'msg' => '',
                'timestamp' => 0,
                'total' => 0,
            ]),
    ]);

    expect(app(PersonnelClient::class)->refreshCache())->toBe(1)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->eibNumCn())->toBe('10001')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->eibNameCn())->toBe('张三')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->departmentId())->toBe('DEV01');

    // 外部接口返回空列表时，refreshCache 返回 0，并保留上一次成功同步的缓存。
    expect(app(PersonnelClient::class)->refreshCache())->toBe(0)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三');
});

test('the sso callback exchanges code and creates local session', function (): void {
    // 用容器替换 SsoClient，避免测试真实访问公司 SSO。
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        /**
         * 返回测试用 SSO token。
         *
         * 该方法替代真实 token 接口，验证 callback 收到的 code 会交给后端 SSO 客户端。
         */
        public function exchangeCodeForToken(string $code, string $redirectUri): SsoToken
        {
            expect($code)->toBe('code-123')
                ->and($redirectUri)->toBe(route('sso.callback'));

            return new SsoToken(
                accessToken: 'token-123',
                tokenType: 'bearer',
                expiresIn: 35999,
                expiresAt: now()->addHour()->toImmutable(),
            );
        }

        /**
         * 返回测试用 SSO 登录人。
         *
         * 该方法替代真实总部当前登录人接口，验证 accessToken 会由后端传入。
         */
        public function fetchCurrentUser(string $accessToken): SsoUser
        {
            // 确认 code 换到的 accessToken 被原样传给后端 SSO 当前登录人接口。
            expect($accessToken)->toBe('token-123');

            return new SsoUser(
                employeeNo: 'E10001',
                displayName: '张三',
                departmentId: 'DEV01',
                departmentName: '开发一部',
                avatarId: 'avatar-001',
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

    $this->get('/sso/callback?code=code-123')
        ->assertRedirect(route('tasks.index'));

    // 登录成功后，用户快照、TaskHub 角色和 token 快照都应写入 Session。
    expect(session(CurrentUserService::SESSION_KEY)['employeeNo'])->toBe('E10001')
        ->and(session(CurrentUserService::SESSION_KEY)['avatarId'])->toBe('avatar-001')
        ->and(session(CurrentUserService::ROLE_SESSION_KEY))->toBe(['TOP'])
        ->and(session(CurrentUserService::TOKEN_SESSION_KEY)['accessToken'])->toBe('token-123');
});

test('sso session prefers local personnel list when user belongs to current site', function (): void {
    // 总部 SSO 返回的信息可能不完整，这里故意只返回姓名，不返回部门。
    $this->app->instance(SsoClient::class, new class extends SsoClient
    {
        /**
         * 返回测试用 SSO token。
         *
         * 本测试重点验证本据点人员信息补充，因此 token 接口只返回固定 accessToken。
         */
        public function exchangeCodeForToken(string $code, string $redirectUri): SsoToken
        {
            expect($code)->toBe('code-456')
                ->and($redirectUri)->toBe(route('sso.callback'));

            return new SsoToken(
                accessToken: 'token-456',
                tokenType: 'bearer',
                expiresIn: 35999,
                expiresAt: now()->addHour()->toImmutable(),
            );
        }

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
                department: '开发一部',
                deptInfoList: [
                    [
                        'obiCode' => 'DEV01',
                        'obiName' => '开发一部',
                    ],
                ],
                eibNameCn: '张三',
                eibNumCn: '10001',
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

    $this->get('/sso/callback?code=code-456')->assertRedirect(route('tasks.index'));

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
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
        'taskhub.session_marker' => 'old-session',
    ])
        ->post('/logout')
        ->assertRedirect(route('home'))
        // invalidate() 后旧 Session 数据都不应继续存在。
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::TOKEN_SESSION_KEY)
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
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
        'taskhub.session_marker' => 'old-session',
    ])
        ->post('/logout')
        ->assertRedirect('https://sso.example.test/logout')
        // 即使跳转到外部退出地址，本地 Session 也必须已经清理。
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::TOKEN_SESSION_KEY)
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

test('sso client exchanges authorization code with form request', function (): void {
    // 授权码换 token 接口使用 application/x-www-form-urlencoded，不是 JSON。
    config([
        'sso.base_url' => 'https://sso.example.test',
        'sso.client_id' => 'ClientID',
        'sso.client_secret' => 'secret',
        'sso.token_path' => '/auth/oauth/token',
        'sso.timeout' => 3,
        'sso.verify_ssl' => false,
    ]);

    Http::fake([
        'https://sso.example.test/auth/oauth/token' => Http::response([
            'code' => '00000',
            'data' => [
                'access_token' => 'token-123',
                'token_type' => 'bearer',
                'refresh_token' => 'refresh-123',
                'expires_in' => 35999,
                'scope' => 'all',
                'user_id' => '1384064503753146370',
                'jti' => '8da795ab-d22f-4598-b081-afe99e8840ce',
            ],
            'msg' => 'OK',
            'timestamp' => 1788680326193,
        ]),
    ]);

    $token = app(SsoClient::class)->exchangeCodeForToken('code-123', 'http://127.0.0.1:8000/sso/callback');

    expect($token->accessToken())->toBe('token-123')
        ->and($token->tokenType())->toBe('bearer')
        ->and($token->refreshToken())->toBe('refresh-123')
        ->and($token->expiresIn())->toBe(35999)
        ->and($token->isValid())->toBeTrue();

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://sso.example.test/auth/oauth/token'
        && str_contains($request->header('Content-Type')[0] ?? '', 'application/x-www-form-urlencoded')
        && $request->data()['code'] === 'code-123'
        && $request->data()['client_id'] === 'ClientID'
        && $request->data()['client_secret'] === 'secret'
        && $request->data()['redirect_uri'] === 'http://127.0.0.1:8000/sso/callback'
        && $request->data()['grant_type'] === 'authorization_code');
});

test('sso client sends bearer token header to user info endpoint', function (): void {
    // 当前人员信息接口通过 Authorization Header 识别登录人，默认使用 GET 请求。
    config([
        'sso.base_url' => 'https://sso.example.test',
        'sso.client_id' => 'ClientID',
        'sso.client_secret' => 'secret',
        'sso.userinfo_path' => '/api/current-user',
        'sso.userinfo_method' => 'GET',
        'sso.timeout' => 3,
        'sso.verify_ssl' => false,
    ]);

    // Http::fake 拦截 Laravel HTTP Client 请求，不会真实访问网络。
    Http::fake([
        'https://sso.example.test/api/current-user' => Http::response([
            'code' => '',
            'data' => [
                [
                    'empCnNum' => 'E10001',
                    'empName' => '张三',
                    'empPhoto' => 'avatar-001',
                    'deptInfoList' => [
                        [
                            'copSort' => 9,
                            'obiCode' => 'DEV09',
                            'obiName' => '开发九部',
                        ],
                        [
                            'copSort' => 2,
                            'obiCode' => 'DEV02',
                            'obiName' => '开发二部',
                        ],
                    ],
                ],
            ],
            'msg' => '',
            'timestamp' => 0,
            'total' => 1,
        ]),
    ]);

    $user = app(SsoClient::class)->fetchCurrentUser('token-123');

    expect($user->employeeNo())->toBe('E10001')
        ->and($user->departmentId())->toBe('DEV02')
        ->and($user->departmentName())->toBe('开发二部')
        ->and($user->avatarId())->toBe('avatar-001');

    // 断言请求方法、地址和 Authorization Header，防止后续改动破坏总部接口协议。
    Http::assertSent(fn ($request): bool => $request->method() === 'GET'
        && $request->url() === 'https://sso.example.test/api/current-user'
        && ($request->header('Authorization')[0] ?? '') === 'bearer token-123');
});

test('sso user parses current employee list payload', function (): void {
    // 当前人员信息接口第一层包含 code 和 data，人员字段在 data 第一条记录中。
    $user = SsoUser::fromPayload([
        'code' => '',
        'data' => [
            [
                'empCnNum' => 'E10001',
                'empName' => '张三',
                'empNameCn' => '张三',
                'empJpNum' => 'JP10001',
                'empPhoto' => 'avatar-002',
                'deptInfoList' => [
                    [
                        'copSort' => 3,
                        'obiCode' => 'DEV03',
                        'obiName' => '开发三部',
                    ],
                    [
                        'copSort' => 1,
                        'obiCode' => 'DEV01',
                        'obiName' => '开发一部',
                    ],
                ],
            ],
        ],
        'msg' => '',
        'timestamp' => 0,
        'total' => 1,
    ]);

    expect($user->employeeNo())->toBe('E10001')
        ->and($user->displayName())->toBe('张三')
        ->and($user->departmentId())->toBe('DEV01')
        ->and($user->departmentName())->toBe('开发一部')
        ->and($user->avatarId())->toBe('avatar-002')
        ->and($user->raw())->toHaveKey('data');
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

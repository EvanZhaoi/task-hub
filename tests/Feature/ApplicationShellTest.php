<?php

use App\Integrations\FileOperation\FileUploadClient;
use App\Integrations\FileOperation\UploadedFileRef;
use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoToken;
use App\Integrations\Sso\SsoUser;
use App\Mail\BidSelectedMail;
use App\Mail\BidSubmittedMail;
use App\Models\Task;
use App\Models\TaskhubUserRole;
use App\Services\CurrentUserService;
use App\Services\SnowflakeId;
use App\Services\TaskhubRoleService;
use App\Services\TaskReturnNavigation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
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
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-123');

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

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回发布弹窗直接指派模式需要的测试人员选项。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-123');

            return [new PersonnelUser(eibNameCn: '王开发', eibNumCn: 'E20001')];
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
        'is_urgent' => true,
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
            // 邮箱和外部原始数据只留在后端 Session，不应进入 Inertia Props。
            'email' => 'lilei@example.test',
            'raw' => ['sensitiveField' => 'internal-only'],
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
        ->assertJsonPath('props.personnelOptions.0.employeeNo', 'E20001')
        ->assertJsonMissingPath('props.auth.user.email')
        ->assertJsonMissingPath('props.auth.user.raw')
        ->assertJsonPath('props.tasks.data.0.displayStatus', 'PENDING_SELECTION')
        ->assertJsonPath('props.tasks.data.0.amountType', 'BUDGET')
        ->assertJsonPath('props.tasks.data.0.isUrgent', true)
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 1)
        ->assertJsonPath('props.tasks.data.0.bidCount', 1);
});

test('authenticated users can publish a bidding task with attachment id and name', function (): void {
    $this->withoutVite();
    createTaskHallTables();
    config(['file_operation.download_url_prefix' => 'https://files.example.test/download?id=']);

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回测试用付款账号。
         *
         * 发布任务时后端会按账号 ID 从付款账号列表中筛选账号，本方法用来隔离外部接口依赖。
         */
        public function fetchById(string $accountId, ?string $accessToken = null): PaymentAccount
        {
            expect($accountId)->toBe('PAY001')
                ->and($accessToken)->toBe('token-123');

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
            'assignmentType' => 'BIDDING',
            'title' => '报表导出优化',
            'description' => '<h2>优化目标</h2><p onclick="alert(1)">保留<strong>重点</strong>说明</p><script>alert(1)</script><a href="javascript:alert(1)">危险链接</a><a href="https://example.com">安全链接</a>',
            'budget' => '3000.00',
            'expectedDelivery' => now()->addDays(7)->toDateString(),
            'biddingDeadline' => now()->addDay()->format('Y-m-d\TH:i'),
            'complexity' => 'MEDIUM',
            'isUrgent' => true,
            'paymentAccountId' => 'PAY001',
            'attachments' => [
                ['id' => 'ATT001', 'name' => '需求说明.pdf'],
                ['id' => 'ATT002', 'name' => '设计图.png'],
                // 故意重复一个附件 ID，验证后端按 ID 去重并保留第一次出现的文件名。
                ['id' => 'ATT001', 'name' => '重复附件名.pdf'],
            ],
        ])
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHas('success', '任务已发布。');

    $task = DB::table('task')->where('title', '报表导出优化')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('OPEN')
        ->and($task->assignment_type)->toBe('BIDDING')
        ->and((bool) $task->is_urgent)->toBeTrue()
        ->and($task->created_by)->toBe('E10002');

    expect($task->description)
        // 富文本允许的标签会保留，用于前端 RichTextViewer 展示。
        ->toContain('<h2>优化目标</h2>')
        ->toContain('<strong>重点</strong>')
        ->toContain('href="https://example.com"')
        // 危险标签、事件属性和 javascript: URL 必须在后端入库前被清理。
        ->not->toContain('<script')
        ->not->toContain('onclick')
        ->not->toContain('javascript:');

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

    expect(DB::table('attachment_ref')
        ->where('owner_type', 'TASK')
        ->where('owner_id', $task->id)
        ->orderBy('attachment_id')
        ->get(['attachment_id', 'attachment_name'])
        ->map(fn (object $row): array => [
            'id' => $row->attachment_id,
            'name' => $row->attachment_name,
        ])
        ->all())->toBe([
            ['id' => 'ATT001', 'name' => '需求说明.pdf'],
            ['id' => 'ATT002', 'name' => '设计图.png'],
        ]);

    expect(DB::table('task_event')->where('task_id', $task->id)->orderBy('id')->pluck('event_type')->all())
        ->toBe(['TASK_CREATED', 'TASK_PUBLISHED']);

    // 发布与详情必须作为一条完整链路验证，防止附件虽然入库但详情关联条件不一致。
    $this->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/'.$task->id)
        ->assertOk()
        ->assertJsonPath('props.task.attachments.0.id', 'ATT001')
        ->assertJsonPath('props.task.attachments.0.name', '需求说明.pdf')
        ->assertJsonPath('props.task.attachments.0.url', 'https://files.example.test/download?id=ATT001')
        ->assertJsonPath('props.task.attachments.1.id', 'ATT002')
        ->assertJsonPath('props.task.attachments.1.name', '设计图.png')
        ->assertJsonPath('props.task.attachments.1.url', 'https://files.example.test/download?id=ATT002');
});

test('authenticated users can publish a direct task with owner and collaborators', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回直接指派测试使用的付款账号快照来源。
         */
        public function fetchById(string $accountId, ?string $accessToken = null): PaymentAccount
        {
            expect($accountId)->toBe('PAY001')
                ->and($accessToken)->toBe('token-123');

            return new PaymentAccount(
                wbaAccountCode: 'PAY001',
                wbaAccountName: '直接指派预算',
                deptName: '开发一部',
            );
        }
    });

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回可被指派的负责人和协作成员，隔离真实人员接口。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-123');

            return [
                new PersonnelUser(eibNameCn: '主开发', eibNumCn: 'E20001'),
                new PersonnelUser(eibNameCn: '协作开发甲', eibNumCn: 'E20002'),
                new PersonnelUser(eibNameCn: '协作开发乙', eibNumCn: 'E20003'),
            ];
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
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->post('/tasks', [
            'assignmentType' => 'DIRECT',
            'title' => '直接指派任务',
            'description' => '<p>由指定成员直接执行。</p>',
            'budget' => '5000.00',
            'expectedDelivery' => now()->addDays(10)->toDateString(),
            // DIRECT 不提交 biddingDeadline，后端也不会为它保存招标截止时间。
            'complexity' => 'HIGH',
            'isUrgent' => false,
            'paymentAccountId' => 'PAY001',
            'ownerEmployeeNo' => 'E20001',
            'collaboratorEmployeeNos' => ['E20002', 'E20003'],
            'attachments' => [
                ['id' => 'ATT-DIRECT-001', 'name' => '直接指派说明.pdf'],
            ],
        ])
        ->assertRedirect(route('tasks.index'))
        ->assertSessionHas('success', '任务已发布。');

    $task = DB::table('task')->where('title', '直接指派任务')->first();

    expect($task)->not->toBeNull()
        ->and($task->status)->toBe('ASSIGNED')
        ->and($task->assignment_type)->toBe('DIRECT')
        ->and($task->assigned_bid_id)->toBeNull()
        ->and($task->primary_assignee_id)->toBe('E20001')
        ->and($task->bidding_deadline)->toBeNull()
        ->and((float) $task->final_amount)->toBe(5000.0)
        ->and($task->final_delivery)->toBe(now()->addDays(10)->toDateString());

    expect(DB::table('task_assignee')
        ->where('task_id', $task->id)
        ->orderBy('user_id')
        ->get(['user_id', 'role'])
        ->map(fn (object $row): array => ['userId' => $row->user_id, 'role' => $row->role])
        ->all())->toBe([
            ['userId' => 'E20001', 'role' => 'OWNER'],
            ['userId' => 'E20002', 'role' => 'COLLABORATOR'],
            ['userId' => 'E20003', 'role' => 'COLLABORATOR'],
        ]);

    expect(DB::table('bid')->where('task_id', $task->id)->exists())->toBeFalse()
        ->and(DB::table('task_event')->where('task_id', $task->id)->orderBy('id')->pluck('event_type')->all())
        ->toBe(['TASK_CREATED', 'TASK_ASSIGNED'])
        ->and(DB::table('attachment_ref')->where('owner_type', 'TASK')->where('owner_id', $task->id)->first())
        ->toMatchObject([
            'attachment_id' => 'ATT-DIRECT-001',
            'attachment_name' => '直接指派说明.pdf',
        ]);

    // 再访问详情页，确认正式成员和成交金额能沿用现有详情展示链路。
    $this->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/'.$task->id)
        ->assertOk()
        ->assertJsonPath('props.task.assignmentType', 'DIRECT')
        ->assertJsonPath('props.task.status', 'ASSIGNED')
        ->assertJsonPath('props.task.amountType', 'FINAL')
        ->assertJsonPath('props.task.assignees.0.userId', 'E20001')
        ->assertJsonPath('props.task.assignees.0.role', 'OWNER')
        ->assertJsonCount(3, 'props.task.assignees')
        ->assertJsonPath('props.canPlaceBid', false)
        ->assertJsonCount(0, 'props.visibleBids');
});

test('direct task publishing rejects members outside the personnel directory', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回有效付款账号，让测试聚焦人员目录校验。
         */
        public function fetchById(string $accountId, ?string $accessToken = null): PaymentAccount
        {
            return new PaymentAccount(wbaAccountCode: $accountId, wbaAccountName: '测试预算');
        }
    });

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 人员目录不包含请求中的负责人，用于验证后端不能信任前端工号。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            return [new PersonnelUser(eibNameCn: '合法人员', eibNumCn: 'E20001')];
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E10002', 'displayName' => '李雷'],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks')
        ->post('/tasks', [
            'assignmentType' => 'DIRECT',
            'title' => '非法负责人任务',
            'budget' => '1000.00',
            'expectedDelivery' => now()->addDays(5)->toDateString(),
            'complexity' => 'LOW',
            'isUrgent' => false,
            'paymentAccountId' => 'PAY001',
            'ownerEmployeeNo' => 'E99999',
            'collaboratorEmployeeNos' => [],
            'attachments' => [],
        ])
        ->assertRedirect('/tasks')
        ->assertSessionHasErrors('ownerEmployeeNo');

    // 人员校验失败发生在事务开始前，数据库中不能留下半成品任务或成员记录。
    expect(DB::table('task')->where('title', '非法负责人任务')->exists())->toBeFalse()
        ->and(DB::table('task_assignee')->exists())->toBeFalse()
        ->and(DB::table('task_event')->exists())->toBeFalse();
});

test('direct task publishing rejects an owner repeated as collaborator', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 返回有效付款账号，让测试聚焦负责人和协作者不能重复的业务规则。
         */
        public function fetchById(string $accountId, ?string $accessToken = null): PaymentAccount
        {
            return new PaymentAccount(wbaAccountCode: $accountId, wbaAccountName: '测试预算');
        }
    });

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回包含目标工号的人员目录，证明失败原因不是人员不存在。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            return [new PersonnelUser(eibNameCn: '主开发', eibNumCn: 'E20001')];
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E10002', 'displayName' => '李雷'],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks')
        ->post('/tasks', [
            'assignmentType' => 'DIRECT',
            'title' => '成员重复任务',
            'budget' => '1000.00',
            'expectedDelivery' => now()->addDays(5)->toDateString(),
            'complexity' => 'LOW',
            'isUrgent' => false,
            'paymentAccountId' => 'PAY001',
            'ownerEmployeeNo' => 'E20001',
            'collaboratorEmployeeNos' => ['E20001'],
            'attachments' => [],
        ])
        ->assertRedirect('/tasks')
        ->assertSessionHasErrors('collaboratorEmployeeNos');

    expect(DB::table('task')->where('title', '成员重复任务')->exists())->toBeFalse();
});

test('authenticated users can upload task attachment through taskhub backend', function (): void {
    $this->app->instance(FileUploadClient::class, new class extends FileUploadClient
    {
        /**
         * 模拟总部文件上传接口。
         *
         * 该测试只验证 TaskHub 后端会用当前 Session 中的动态 accessToken 调用上传 Client。
         */
        public function upload(UploadedFile $file, string $accessToken): UploadedFileRef
        {
            expect($file->getClientOriginalName())->toBe('需求说明.pdf')
                ->and($accessToken)->toBe('token-upload');

            return new UploadedFileRef(
                id: 'ATT-UPLOAD-001',
                name: $file->getClientOriginalName(),
            );
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
        ],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-upload',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->postJson('/attachments/upload', [
            'file' => UploadedFile::fake()->create('需求说明.pdf', 128, 'application/pdf'),
        ])
        ->assertOk()
        ->assertJsonPath('id', 'ATT-UPLOAD-001')
        ->assertJsonPath('name', '需求说明.pdf');
});

test('file upload client sends multipart request and reads data id and name', function (): void {
    config([
        'file_operation.base_url' => 'https://files.example.test',
        'file_operation.upload_path' => '/file-operation/file/upload',
        'file_operation.service_key' => 'TASKHUB',
        'file_operation.timeout' => 10,
        'file_operation.connect_timeout' => 5,
        'file_operation.verify_ssl' => false,
    ]);

    Http::fake([
        'https://files.example.test/file-operation/file/upload' => Http::response([
            'code' => '',
            'data' => [
                // 总部上传接口确认使用 data.id / data.name，不能读取 fileId。
                'id' => 'ATT-REMOTE-001',
                'name' => '总部原型图.png',
            ],
            'msg' => '',
            'timestamp' => 0,
        ]),
    ]);

    $uploadedFile = app(FileUploadClient::class)->upload(
        // 使用超过 1 MiB 的文件覆盖 Guzzle 默认启用 Expect: 100-Continue 的边界。
        UploadedFile::fake()->create('原型图.png', 1100, 'image/png'),
        'token-file',
    );

    expect($uploadedFile->id())->toBe('ATT-REMOTE-001')
        ->and($uploadedFile->name())->toBe('总部原型图.png');

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://files.example.test/file-operation/file/upload'
        && ($request->header('Authorization')[0] ?? '') === 'bearer token-file'
        // 总部网关无法正确处理大文件的 100-Continue 握手，上传请求不得携带该 Header。
        && $request->header('Expect') === []
        // 文件流可计算长度，因此应使用 Content-Length，而不是未知长度的 chunked 请求体。
        && is_numeric($request->header('Content-Length')[0] ?? null)
        && $request->header('Transfer-Encoding') === []
        && collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'serviceKey'
            && ($part['contents'] ?? null) === 'TASKHUB')
        && collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'businessKey'
            && ($part['contents'] ?? null) === '')
        && collect($request->data())->contains(fn (array $part): bool => ($part['name'] ?? null) === 'modelKey'
            && ($part['contents'] ?? null) === '')
        && $request->hasFile('file', filename: '原型图.png'));
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

    $paymentAccount = app(PaymentAccountClient::class)->fetchById('PAY001', 'token-pay');

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
        && $request->url() === 'https://payment.example.test/accounts'
        && ($request->header('Authorization')[0] ?? '') === 'bearer token-pay');
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

    expect(app(PaymentAccountClient::class)->refreshCache('token-pay'))->toBe(1)
        ->and(app(PaymentAccountClient::class)->fetchAll('token-pay')[0]->accountId())->toBe('PAY001');

    // 外部接口返回空列表时，refreshCache 返回 0，并保留上一次成功同步的缓存。
    expect(app(PaymentAccountClient::class)->refreshCache('token-pay'))->toBe(0)
        ->and(app(PaymentAccountClient::class)->fetchAll('token-pay')[0]->accountId())->toBe('PAY001');

    Http::assertSent(fn ($request): bool => ($request->header('Authorization')[0] ?? '') === 'bearer token-pay');
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

    expect(app(PersonnelClient::class)->refreshCache('token-personnel'))->toBe(1)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->eibNumCn())->toBe('10001')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->eibNameCn())->toBe('张三')
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->departmentId())->toBe('DEV01');

    // 外部接口返回空列表时，refreshCache 返回 0，并保留上一次成功同步的缓存。
    expect(app(PersonnelClient::class)->refreshCache('token-personnel'))->toBe(0)
        ->and(app(PersonnelClient::class)->findByEmployeeNo('00010001')?->displayName())->toBe('张三');

    Http::assertSent(fn ($request): bool => ($request->header('Authorization')[0] ?? '') === 'bearer token-personnel');
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
        public function findByEmployeeNo(string $employeeNo, ?string $accessToken = null): ?PersonnelUser
        {
            // 本测试只验证 SSO 建 Session 主流程，人员列表返回 null 表示不属于本据点，继续使用总部信息。
            expect($employeeNo)->toBe('E10001')
                ->and($accessToken)->toBe('token-123');

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
        public function findByEmployeeNo(string $employeeNo, ?string $accessToken = null): ?PersonnelUser
        {
            // 本据点工号不带前导 0；PersonnelClient 会负责按本地规则匹配和返回准确人员信息。
            expect($employeeNo)->toBe('00010001')
                ->and($accessToken)->toBe('token-456');

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

test('authenticated users can view task detail page', function (): void {
    $this->withoutVite();
    createTaskHallTables();
    config(['file_operation.download_url_prefix' => 'https://files.example.test/download/']);

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回详情页联合投标选择器使用的测试人员。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-123');

            return [
                new PersonnelUser(eibNameCn: '协作开发', eibNumCn: 'E30001'),
            ];
        }
    });

    DB::table('task')->insert([
        'id' => 10002,
        'title' => '任务详情页开发',
        'description' => '<p>展示完整任务信息。</p>',
        'payment_account_id' => 'PAY001',
        'payment_account_snapshot' => json_encode([
            'accountName' => '开发预算',
            'departmentName' => '开发部',
        ], JSON_THROW_ON_ERROR),
        'budget' => 1200,
        'expected_delivery' => now()->addDays(5)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'is_urgent' => false,
        'created_by' => 'E10001',
        'created_by_snapshot' => json_encode([
            'displayName' => '陈PM',
            'departmentName' => '产品部',
        ], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('attachment_ref')->insert([
        'id' => 30001,
        'owner_type' => 'TASK',
        'owner_id' => 10002,
        'attachment_id' => 'ATT-TASK-001',
        'attachment_name' => '需求说明.pdf',
        'uploaded_by' => 'E10001',
        'created_at' => now(),
    ]);

    DB::table('task_event')->insert([
        'id' => 40001,
        'task_id' => 10002,
        'event_type' => 'TASK_PUBLISHED',
        'operator_id' => 'E10001',
        'from_status' => 'DRAFT',
        'to_status' => 'OPEN',
        'remark' => '任务进入招标中',
        'created_at' => now(),
    ]);

    // 插入其他用户的投标和投标事件，验证普通访问者只能看到数量，不能看到方案或个人履历。
    DB::table('bid')->insert([
        'id' => 20002,
        'task_id' => 10002,
        'amount' => 1100,
        'delivery_date' => now()->addDays(4)->toDateString(),
        'proposal' => '只有发布者和投标成员可以看到的方案。',
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10002:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        'id' => 21001,
        'bid_id' => 20002,
        'user_id' => 'E20001',
        'role' => 'OWNER',
        'created_at' => now(),
    ]);

    DB::table('task_event')->insert([
        'id' => 40002,
        'task_id' => 10002,
        'event_type' => 'BID_SUBMITTED',
        'operator_id' => 'E20001',
        'from_status' => 'OPEN',
        'to_status' => 'OPEN',
        'related_type' => 'BID',
        'related_id' => 20002,
        'remark' => '提交投标',
        'created_at' => now()->addSecond(),
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10002?returnTo='.urlencode('/my-tasks/bids?status=ACTIVE&page=2'))
        ->assertOk()
        ->assertJsonPath('component', 'Tasks/Show')
        ->assertJsonPath('props.task.id', '10002')
        ->assertJsonPath('props.task.title', '任务详情页开发')
        ->assertJsonPath('props.task.amountType', 'BUDGET')
        ->assertJsonPath('props.task.isUrgent', false)
        ->assertJsonPath('props.task.attachments.0.name', '需求说明.pdf')
        ->assertJsonPath('props.task.attachments.0.url', 'https://files.example.test/download/ATT-TASK-001')
        ->assertJsonPath('props.events.0.eventType', 'TASK_PUBLISHED')
        ->assertJsonCount(1, 'props.events')
        ->assertJsonPath('props.bidOverview.activeCount', 1)
        ->assertJsonPath('props.bidOverview.totalCount', 1)
        ->assertJsonPath('props.bidOverview.visibleCount', 0)
        ->assertJsonPath('props.bidOverview.hiddenCount', 1)
        ->assertJsonCount(0, 'props.visibleBids')
        ->assertJsonPath('props.personnelOptions.0.employeeNo', 'E30001')
        ->assertJsonPath('props.personnelOptions.0.displayName', '协作开发')
        ->assertJsonPath('props.canPlaceBid', true)
        ->assertJsonPath('props.navigation.activeNav', 'my-tasks')
        ->assertJsonPath('props.navigation.backLabel', '返回我的投标')
        ->assertJsonPath('props.navigation.backUrl', '/my-tasks/bids?status=ACTIVE&page=2');
});

test('authenticated users can place a bid for an open task', function (): void {
    $this->withoutVite();
    createTaskHallTables();
    config(['file_operation.download_url_prefix' => 'https://files.example.test/download/']);
    Mail::fake();

    DB::table('task')->insert([
        'id' => 10003,
        'title' => '投标功能开发',
        'description' => '实现提交投标。',
        'payment_account_id' => 'PAY001',
        'budget' => 2000,
        'expected_delivery' => now()->addDays(8)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'HIGH',
        'created_by' => 'E10001',
        'created_by_snapshot' => json_encode([
            'displayName' => '任务发布者',
            'email' => 'publisher@example.test',
        ], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E20001',
            'displayName' => '王开发',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->post('/tasks/10003/bids', [
            'amount' => '1800.00',
            'deliveryDate' => now()->addDays(6)->toDateString(),
            'proposal' => '我会先完成接口，再补充页面联调。',
            'attachments' => [
                ['id' => 'ATT-BID-001', 'name' => '投标方案.pdf'],
            ],
            'returnTo' => '/my-tasks/bids?status=ACTIVE',
        ])
        ->assertRedirect(route('tasks.show', [
            'task' => 10003,
            'returnTo' => '/my-tasks/bids?status=ACTIVE',
        ]))
        ->assertSessionHas('success', '投标已提交。');

    $bid = DB::table('bid')->where('task_id', 10003)->first();

    expect($bid)->not->toBeNull()
        // SQLite 测试库会把 decimal 读成数字，MySQL 中 DECIMAL 通常读成字符串；这里按数值语义断言。
        ->and((float) $bid->amount)->toBe(1800.0)
        ->and($bid->status)->toBe('ACTIVE')
        ->and($bid->active_key)->toBe('10003:E20001')
        ->and($bid->revision_no)->toBe(1);

    expect(DB::table('bid_member')->where('bid_id', $bid->id)->first())
        ->toMatchObject([
            'user_id' => 'E20001',
            'role' => 'OWNER',
        ]);

    expect(DB::table('attachment_ref')->where('owner_type', 'BID')->where('owner_id', $bid->id)->first())
        ->toMatchObject([
            'attachment_id' => 'ATT-BID-001',
            'attachment_name' => '投标方案.pdf',
        ]);

    expect(DB::table('task_event')->where('task_id', 10003)->where('event_type', 'BID_SUBMITTED')->exists())
        ->toBeTrue();

    // 投标事务提交后给任务发布者发送邮件；不把其他人员邮箱暴露为收件人。
    Mail::assertSent(BidSubmittedMail::class, function (BidSubmittedMail $mail): bool {
        return $mail->hasTo('publisher@example.test')
            && $mail->taskTitle === '投标功能开发'
            && $mail->bidderName === '王开发'
            && $mail->amount === '¥1,800.00';
    });

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回投标人展示信息，验证详情页会同时组装投标成员和附件。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-123');

            return [new PersonnelUser(eibNameCn: '王开发', eibNumCn: 'E20001')];
        }
    });

    // 从真实详情接口读取刚创建的投标，防止附件只入库但 Eloquent 关联条件错误。
    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E20001',
            'displayName' => '王开发',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-123',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10003')
        ->assertOk()
        ->assertJsonPath('props.visibleBids.0.members.0.displayName', '王开发')
        ->assertJsonPath('props.visibleBids.0.attachments.0.id', 'ATT-BID-001')
        ->assertJsonPath('props.visibleBids.0.attachments.0.name', '投标方案.pdf')
        ->assertJsonPath(
            'props.visibleBids.0.attachments.0.url',
            'https://files.example.test/download/ATT-BID-001',
        )
        ->assertJsonPath('props.bidOverview.activeCount', 1)
        ->assertJsonPath('props.bidOverview.totalCount', 1)
        ->assertJsonPath('props.bidOverview.visibleCount', 1)
        ->assertJsonPath('props.bidOverview.hiddenCount', 0)
        ->assertJsonPath('props.events.0.eventType', 'BID_SUBMITTED');
});

test('authenticated users can place a joint bid with collaborators', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    DB::table('task')->insert([
        'id' => 10004,
        'title' => '联合投标功能开发',
        'description' => '验证主投标人与协作成员共同投标。',
        'payment_account_id' => 'PAY001',
        'budget' => 5000,
        'expected_delivery' => now()->addDays(10)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'HIGH',
        'created_by' => 'E10001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回可参与联合投标的本据点人员，隔离真实外部人员接口。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-joint-bid');

            return [
                new PersonnelUser(eibNameCn: '协作开发甲', eibNumCn: 'E30001'),
                new PersonnelUser(eibNameCn: '协作开发乙', eibNumCn: 'E30002'),
            ];
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E20001',
            'displayName' => '主投标人',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-joint-bid',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->post('/tasks/10004/bids', [
            'amount' => '4500.00',
            'deliveryDate' => now()->addDays(7)->toDateString(),
            'proposal' => '三人协作完成开发和测试。',
            'collaboratorEmployeeNos' => ['E30001', 'E30002'],
        ])
        ->assertRedirect(route('tasks.show', 10004))
        ->assertSessionHas('success', '投标已提交。');

    $bid = DB::table('bid')->where('task_id', 10004)->first();
    $members = DB::table('bid_member')
        ->where('bid_id', $bid->id)
        ->orderBy('role', 'desc')
        ->orderBy('user_id')
        ->get(['user_id', 'role'])
        ->map(fn (object $member): array => [
            'userId' => $member->user_id,
            'role' => $member->role,
        ])
        ->all();

    expect($members)->toBe([
        ['userId' => 'E20001', 'role' => 'OWNER'],
        ['userId' => 'E30001', 'role' => 'COLLABORATOR'],
        ['userId' => 'E30002', 'role' => 'COLLABORATOR'],
    ]);

    $eventData = DB::table('task_event')
        ->where('task_id', 10004)
        ->where('event_type', 'BID_SUBMITTED')
        ->value('event_data');

    expect(json_decode($eventData, true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
        'memberCount' => 3,
        'collaboratorCount' => 2,
    ]);

    // 再请求详情页，验证数据库成员能通过人员目录转换为页面展示名称。
    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E20001',
            'displayName' => '主投标人',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-joint-bid',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10004')
        ->assertOk()
        ->assertJsonPath('props.visibleBids.0.members.0.role', 'OWNER')
        ->assertJsonPath('props.visibleBids.0.members.1.displayName', '协作开发甲')
        ->assertJsonPath('props.visibleBids.0.members.2.displayName', '协作开发乙');
});

test('bid owner can withdraw and submit a new revision while history is preserved', function (): void {
    $this->withoutVite();
    createTaskHallTables();
    Mail::fake();

    DB::table('task')->insert([
        'id' => 10010,
        'title' => '撤回重投功能测试',
        'description' => '验证撤回后保留历史，并允许主投标人提交新版本。',
        'payment_account_id' => 'PAY001',
        'budget' => 5000,
        'expected_delivery' => now()->addDays(10)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        // 提供发布者邮箱快照，重投邮件测试不需要访问真实外部人员接口。
        'created_by_snapshot' => json_encode([
            'displayName' => '任务发布者',
            'email' => 'publisher@example.test',
        ], JSON_THROW_ON_ERROR),
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid')->insert([
        'id' => 21010,
        'task_id' => 10010,
        'amount' => 4600,
        'delivery_date' => now()->addDays(8)->toDateString(),
        'proposal' => '第一版投标方案',
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10010:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        ['id' => 22010, 'bid_id' => 21010, 'user_id' => 'E20001', 'role' => 'OWNER', 'created_at' => now()],
        ['id' => 22011, 'bid_id' => 21010, 'user_id' => 'E20002', 'role' => 'COLLABORATOR', 'created_at' => now()],
    ]);

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回详情页展示所需的投标成员，不访问真实人员接口。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-withdraw-owner');

            return [
                new PersonnelUser(eibNameCn: '主投标人', eibNumCn: 'E20001'),
                new PersonnelUser(eibNameCn: '协作成员', eibNumCn: 'E20002'),
            ];
        }
    });

    $session = [
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E20001',
            'displayName' => '主投标人',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-withdraw-owner',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ];

    // 页面权限结果只允许 OWNER 撤回当前 ACTIVE 投标。
    $this->withSession($session)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10010')
        ->assertOk()
        ->assertJsonPath('props.visibleBids.0.canWithdraw', true);

    $this->withSession($session)
        ->post('/tasks/10010/bids/21010/withdraw', [
            'returnTo' => '/my-tasks/bids?status=ACTIVE',
        ])
        ->assertRedirect(route('tasks.show', [
            'task' => 10010,
            'returnTo' => '/my-tasks/bids?status=ACTIVE',
        ]))
        ->assertSessionHas('success', '投标已撤回，你可以在招标截止前重新投标。');

    $withdrawnBid = DB::table('bid')->where('id', 21010)->first();

    expect($withdrawnBid)->toMatchObject([
        'status' => 'WITHDRAWN',
        'active_key' => null,
        'revision_no' => 1,
    ])->and($withdrawnBid->withdrawn_at)->not->toBeNull();

    $withdrawnEvent = DB::table('task_event')
        ->where('task_id', 10010)
        ->where('event_type', 'BID_WITHDRAWN')
        ->first();

    expect($withdrawnEvent)->not->toBeNull()
        ->and($withdrawnEvent->operator_id)->toBe('E20001')
        ->and($withdrawnEvent->related_id)->toBe(21010);

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 撤回后的列表统计测试不需要访问真实付款账号接口。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            return [];
        }
    });

    // 撤回记录保留在详情历史中，但任务大厅不能继续把它计算为有效投标数量。
    $this->withSession($session)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks?keyword='.urlencode('撤回重投功能测试'))
        ->assertOk()
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 0)
        ->assertJsonPath('props.tasks.data.0.bidCount', 0);

    // 重复撤回必须失败，且不能重复生成审计事件。
    $this->withSession($session)
        ->from('/tasks/10010')
        ->post('/tasks/10010/bids/21010/withdraw')
        ->assertRedirect('/tasks/10010')
        ->assertSessionHasErrors('bid');

    expect(DB::table('task_event')->where('event_type', 'BID_WITHDRAWN')->count())->toBe(1);

    // 撤回释放 active_key 后，主投标人可以新建第 2 版投标，旧记录不能被覆盖。
    $this->withSession($session)
        ->post('/tasks/10010/bids', [
            'amount' => '4400.00',
            'deliveryDate' => now()->addDays(7)->toDateString(),
            'proposal' => '调整后的第二版投标方案',
            'collaboratorEmployeeNos' => [],
        ])
        ->assertRedirect(route('tasks.show', 10010))
        ->assertSessionHas('success', '投标已提交。');

    $bids = DB::table('bid')
        ->where('task_id', 10010)
        ->orderBy('revision_no')
        ->get();

    expect($bids)->toHaveCount(2)
        ->and($bids[0]->status)->toBe('WITHDRAWN')
        ->and($bids[0]->revision_no)->toBe(1)
        ->and($bids[1]->status)->toBe('ACTIVE')
        ->and($bids[1]->revision_no)->toBe(2)
        ->and($bids[1]->active_key)->toBe('10010:E20001');

    // 详情接口同时返回当前有效版本和已撤回历史，前端负责分区并默认折叠历史。
    $this->withSession($session)
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10010')
        ->assertOk()
        ->assertJsonPath('props.bidOverview.activeCount', 1)
        ->assertJsonPath('props.bidOverview.totalCount', 1)
        ->assertJsonPath('props.bidOverview.visibleCount', 1)
        ->assertJsonPath('props.task.activeBidCount', 1)
        ->assertJsonPath('props.visibleBids.0.status', 'ACTIVE')
        ->assertJsonPath('props.visibleBids.0.revisionNo', 2)
        ->assertJsonPath('props.visibleBids.0.canWithdraw', true)
        ->assertJsonPath('props.visibleBids.1.status', 'WITHDRAWN')
        ->assertJsonPath('props.visibleBids.1.canWithdraw', false);

    // 无关用户只得到当前有效投标数，不会从 Props 中获知已撤回历史数量。
    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E90001', 'displayName' => '无关用户'],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-withdraw-owner',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10010')
        ->assertOk()
        ->assertJsonPath('props.bidOverview.activeCount', 1)
        ->assertJsonPath('props.bidOverview.totalCount', 1)
        ->assertJsonPath('props.bidOverview.visibleCount', 0)
        ->assertJsonPath('props.bidOverview.hiddenCount', 1)
        ->assertJsonCount(0, 'props.visibleBids');
});

test('bid collaborator cannot withdraw the owner bid', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    DB::table('task')->insert([
        'id' => 10011,
        'title' => '协作者撤回权限测试',
        'description' => '验证协作者不能撤回整个联合投标。',
        'payment_account_id' => 'PAY001',
        'budget' => 3000,
        'expected_delivery' => now()->addDays(10)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid')->insert([
        'id' => 21011,
        'task_id' => 10011,
        'amount' => 2800,
        'delivery_date' => now()->addDays(7)->toDateString(),
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10011:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        ['id' => 22012, 'bid_id' => 21011, 'user_id' => 'E20001', 'role' => 'OWNER', 'created_at' => now()],
        ['id' => 22013, 'bid_id' => 21011, 'user_id' => 'E20002', 'role' => 'COLLABORATOR', 'created_at' => now()],
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E20002', 'displayName' => '协作成员'],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-withdraw-collaborator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10011')
        ->post('/tasks/10011/bids/21011/withdraw')
        ->assertRedirect('/tasks/10011')
        ->assertSessionHasErrors('bid');

    expect(DB::table('bid')->where('id', 21011)->value('status'))->toBe('ACTIVE')
        ->and(DB::table('task_event')->where('event_type', 'BID_WITHDRAWN')->exists())->toBeFalse();
});

test('bid cannot be withdrawn after the bidding deadline', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    DB::table('task')->insert([
        'id' => 10012,
        'title' => '截止后撤回测试',
        'description' => '验证截止后不能撤回有效投标。',
        'payment_account_id' => 'PAY001',
        'budget' => 3000,
        'expected_delivery' => now()->addDays(10)->toDateString(),
        'bidding_deadline' => now()->subMinute(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        'version' => 0,
        'created_at' => now()->subDay(),
        'updated_at' => now(),
    ]);

    DB::table('bid')->insert([
        'id' => 21012,
        'task_id' => 10012,
        'amount' => 2800,
        'delivery_date' => now()->addDays(7)->toDateString(),
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10012:E20001',
        'created_at' => now()->subHour(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        'id' => 22014,
        'bid_id' => 21012,
        'user_id' => 'E20001',
        'role' => 'OWNER',
        'created_at' => now()->subHour(),
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E20001', 'displayName' => '主投标人'],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-withdraw-expired',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10012')
        ->post('/tasks/10012/bids/21012/withdraw')
        ->assertRedirect('/tasks/10012')
        ->assertSessionHasErrors(['bid' => '招标已经截止，不能撤回投标。']);

    expect(DB::table('bid')->where('id', 21012)->value('status'))->toBe('ACTIVE')
        ->and(DB::table('task_event')->where('event_type', 'BID_WITHDRAWN')->exists())->toBeFalse();
});

test('task creator can select a bid and assign all bid members', function (): void {
    $this->withoutVite();
    createTaskHallTables();
    Mail::fake();

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回中标成员的姓名和邮箱，同时供邮件通知与详情成员展示使用。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-task-creator');

            return [
                new PersonnelUser(
                    eibEmail: 'owner@example.test',
                    eibNameCn: '主开发',
                    eibNumCn: 'E20001',
                ),
                new PersonnelUser(
                    eibEmail: 'collaborator@example.test',
                    eibNameCn: '协作开发',
                    eibNumCn: 'E20002',
                ),
            ];
        }
    });

    DB::table('task')->insert([
        'id' => 10006,
        'title' => '选标事务测试',
        'description' => '验证选标后任务、投标、执行成员和事件同时更新。',
        'payment_account_id' => 'PAY001',
        'budget' => 5000,
        'expected_delivery' => now()->addDays(10)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'HIGH',
        'created_by' => 'E10001',
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid')->insert([
        [
            'id' => 21001,
            'task_id' => 10006,
            'amount' => 4500,
            'delivery_date' => now()->addDays(7)->toDateString(),
            'proposal' => '中标方案',
            'status' => 'ACTIVE',
            'revision_no' => 1,
            'active_key' => '10006:E20001',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'id' => 21002,
            'task_id' => 10006,
            'amount' => 4600,
            'delivery_date' => now()->addDays(8)->toDateString(),
            'proposal' => '其他方案',
            'status' => 'ACTIVE',
            'revision_no' => 1,
            'active_key' => '10006:E30001',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('bid_member')->insert([
        [
            'id' => 22001,
            'bid_id' => 21001,
            'user_id' => 'E20001',
            'role' => 'OWNER',
            'created_at' => now(),
        ],
        [
            'id' => 22002,
            'bid_id' => 21001,
            'user_id' => 'E20002',
            'role' => 'COLLABORATOR',
            'created_at' => now(),
        ],
        [
            'id' => 22003,
            'bid_id' => 21002,
            'user_id' => 'E30001',
            'role' => 'OWNER',
            'created_at' => now(),
        ],
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '任务发布者',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-task-creator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->post('/tasks/10006/bids/21001/accept', [
            'returnTo' => '/my-tasks/published?status=PENDING_SELECTION',
        ])
        ->assertRedirect(route('tasks.show', [
            'task' => 10006,
            'returnTo' => '/my-tasks/published?status=PENDING_SELECTION',
        ]))
        ->assertSessionHas('success', '已选定中标方案，任务进入进行中。');

    $task = DB::table('task')->where('id', 10006)->first();

    expect($task)->toMatchObject([
        'status' => 'ASSIGNED',
        'assigned_bid_id' => 21001,
        'primary_assignee_id' => 'E20001',
        'version' => 1,
        'updated_by' => 'E10001',
    ])->and((float) $task->final_amount)->toBe(4500.0)
        ->and(substr((string) $task->final_delivery, 0, 10))->toBe(now()->addDays(7)->toDateString());

    expect(DB::table('bid')->where('id', 21001)->first())
        ->toMatchObject(['status' => 'ACCEPTED', 'active_key' => null]);
    expect(DB::table('bid')->where('id', 21002)->first())
        ->toMatchObject(['status' => 'LOST', 'active_key' => null]);

    $assignees = DB::table('task_assignee')
        ->where('task_id', 10006)
        ->orderBy('user_id')
        ->get(['user_id', 'role'])
        ->map(fn (object $assignee): array => [
            'userId' => $assignee->user_id,
            'role' => $assignee->role,
        ])
        ->all();

    expect($assignees)->toBe([
        ['userId' => 'E20001', 'role' => 'OWNER'],
        ['userId' => 'E20002', 'role' => 'COLLABORATOR'],
    ]);

    expect(DB::table('task_event')->where('task_id', 10006)->where('event_type', 'BID_ACCEPTED')->exists())
        ->toBeTrue()
        ->and(DB::table('task_event')->where('task_id', 10006)->where('event_type', 'TASK_ASSIGNED')->exists())
        ->toBeTrue();

    // OWNER 和 COLLABORATOR 分别收到邮件，避免通过抄送暴露其他成员邮箱。
    Mail::assertSent(BidSelectedMail::class, 2);
    Mail::assertSent(BidSelectedMail::class, function (BidSelectedMail $mail): bool {
        return $mail->hasTo('owner@example.test')
            && $mail->recipientName === '主开发'
            && $mail->memberRole === '负责人'
            && $mail->amount === '¥4,500.00';
    });
    Mail::assertSent(BidSelectedMail::class, function (BidSelectedMail $mail): bool {
        return $mail->hasTo('collaborator@example.test')
            && $mail->recipientName === '协作开发'
            && $mail->memberRole === '协作成员';
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '任务发布者',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-task-creator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks/10006')
        ->assertOk()
        ->assertJsonPath('props.task.status', 'ASSIGNED')
        ->assertJsonPath('props.task.amountType', 'FINAL')
        ->assertJsonPath('props.task.amountLabel', '¥4,500.00')
        ->assertJsonPath('props.task.assignees.0.displayName', '主开发')
        ->assertJsonPath('props.task.assignees.0.role', 'OWNER')
        ->assertJsonPath('props.task.assignees.1.displayName', '协作开发')
        ->assertJsonPath('props.task.assignees.1.role', 'COLLABORATOR')
        // 选标后 ACTIVE 投标为 0，但两条历史投标仍然存在，详情页不能显示成“无人投标”。
        ->assertJsonPath('props.task.activeBidCount', 0)
        ->assertJsonPath('props.bidOverview.activeCount', 0)
        ->assertJsonPath('props.bidOverview.totalCount', 2)
        ->assertJsonPath('props.visibleBids.0.canSelect', false);

    $this->app->instance(PaymentAccountClient::class, new class extends PaymentAccountClient
    {
        /**
         * 列表回归测试无需真实访问总部付款账号接口。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            return [];
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '任务发布者',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-task-creator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks?keyword='.urlencode('选标事务测试'))
        ->assertOk()
        // 列表同样应保留累计投标数，不能把 ACCEPTED/LOST 当成没有投标。
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 0)
        ->assertJsonPath('props.tasks.data.0.bidCount', 2);

    // 第二次提交会在锁内读到 ASSIGNED，必须被拒绝且不能重复写成员或事件。
    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '任务发布者',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-task-creator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10006')
        ->post('/tasks/10006/bids/21001/accept')
        ->assertRedirect('/tasks/10006')
        ->assertSessionHasErrors('bid');

    expect(DB::table('task_assignee')->where('task_id', 10006)->count())->toBe(2)
        ->and(DB::table('task_event')->where('task_id', 10006)->count())->toBe(2);
});

test('users other than the task creator cannot select a bid', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    DB::table('task')->insert([
        'id' => 10007,
        'title' => '选标权限测试',
        'description' => '验证非发布者无法选标。',
        'payment_account_id' => 'PAY001',
        'budget' => 2000,
        'expected_delivery' => now()->addDays(8)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid')->insert([
        'id' => 21003,
        'task_id' => 10007,
        'amount' => 1800,
        'delivery_date' => now()->addDays(6)->toDateString(),
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10007:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        'id' => 22004,
        'bid_id' => 21003,
        'user_id' => 'E20001',
        'role' => 'OWNER',
        'created_at' => now(),
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E99999',
            'displayName' => '其他用户',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-other-user',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10007')
        ->post('/tasks/10007/bids/21003/accept')
        ->assertRedirect('/tasks/10007')
        ->assertSessionHasErrors('bid');

    expect(DB::table('task')->where('id', 10007)->value('status'))->toBe('OPEN')
        ->and(DB::table('bid')->where('id', 21003)->value('status'))->toBe('ACTIVE')
        ->and(DB::table('task_assignee')->where('task_id', 10007)->exists())->toBeFalse()
        ->and(DB::table('task_event')->where('task_id', 10007)->exists())->toBeFalse();
});

test('task creator cannot select a bid that belongs to another task', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $taskDefaults = [
        'description' => '验证 URL 中的任务和投标必须匹配。',
        'payment_account_id' => 'PAY001',
        'budget' => 2000,
        'expected_delivery' => now()->addDays(8)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'MEDIUM',
        'created_by' => 'E10001',
        'version' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('task')->insert([
        ['id' => 10008, 'title' => '任务甲', ...$taskDefaults],
        ['id' => 10009, 'title' => '任务乙', ...$taskDefaults],
    ]);

    DB::table('bid')->insert([
        'id' => 21004,
        'task_id' => 10009,
        'amount' => 1800,
        'delivery_date' => now()->addDays(6)->toDateString(),
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '10009:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        'id' => 22005,
        'bid_id' => 21004,
        'user_id' => 'E20001',
        'role' => 'OWNER',
        'created_at' => now(),
    ]);

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10001',
            'displayName' => '任务发布者',
        ],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-task-creator',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10008')
        ->post('/tasks/10008/bids/21004/accept')
        ->assertRedirect('/tasks/10008')
        ->assertSessionHasErrors('bid');

    expect(DB::table('task')->where('id', 10008)->value('status'))->toBe('OPEN')
        ->and(DB::table('task')->where('id', 10009)->value('status'))->toBe('OPEN')
        ->and(DB::table('bid')->where('id', 21004)->value('status'))->toBe('ACTIVE')
        ->and(DB::table('task_assignee')->exists())->toBeFalse()
        ->and(DB::table('task_event')->exists())->toBeFalse();
});

test('joint bid rejects collaborators outside the personnel directory', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    DB::table('task')->insert([
        'id' => 10005,
        'title' => '非法协作者校验',
        'description' => '验证后端不信任前端工号。',
        'payment_account_id' => 'PAY001',
        'budget' => 1000,
        'expected_delivery' => now()->addDays(5)->toDateString(),
        'bidding_deadline' => now()->addDay(),
        'status' => 'OPEN',
        'assignment_type' => 'BIDDING',
        'complexity' => 'LOW',
        'created_by' => 'E10001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 只返回一个合法人员，验证手工提交的未知工号会被拒绝。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            return [new PersonnelUser(eibNameCn: '合法人员', eibNumCn: 'E30001')];
        }
    });

    $this->withSession([
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E20001', 'displayName' => '主投标人'],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-invalid-member',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ])
        ->from('/tasks/10005')
        ->post('/tasks/10005/bids', [
            'amount' => '900.00',
            'deliveryDate' => now()->addDays(4)->toDateString(),
            'collaboratorEmployeeNos' => ['UNKNOWN'],
        ])
        ->assertRedirect('/tasks/10005')
        ->assertSessionHasErrors('collaboratorEmployeeNos');

    expect(DB::table('bid')->where('task_id', 10005)->exists())->toBeFalse();
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
        ->toThrow(SsoException::class, 'SSO 当前人员接口路径只能填写 path，不能填写完整 URL。');
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
                'empEmail' => 'zhangsan@example.test',
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
        ->and($user->email())->toBe('zhangsan@example.test')
        ->and($user->avatarId())->toBe('avatar-002')
        ->and($user->raw())->toHaveKey('data');
});

test('authenticated users can query the three personal task workspaces', function (): void {
    $this->withoutVite();
    createTaskHallTables();

    $this->app->instance(PersonnelClient::class, new class extends PersonnelClient
    {
        /**
         * 返回个人工作台展示成员名称所需的最小人员列表。
         */
        public function fetchAll(?string $accessToken = null): array
        {
            expect($accessToken)->toBe('token-personal-pages');

            return [
                new PersonnelUser(eibNameCn: '当前用户', eibNumCn: 'E10001'),
                new PersonnelUser(eibNameCn: '任务负责人', eibNumCn: 'E20001'),
            ];
        }
    });

    DB::table('task')->insert([
        [
            'id' => 31001,
            'title' => '我发布的接口任务',
            'description' => '用于验证发布任务工作台。',
            'payment_account_id' => 'PAY001',
            'budget' => 3000,
            'final_amount' => null,
            'expected_delivery' => now()->addDays(8)->toDateString(),
            'final_delivery' => null,
            'bidding_deadline' => now()->addDay(),
            'status' => 'OPEN',
            'assignment_type' => 'BIDDING',
            'complexity' => 'MEDIUM',
            'is_urgent' => false,
            'created_by' => 'E10001',
            'primary_assignee_id' => null,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ],
        [
            'id' => 31002,
            'title' => '协作执行报表任务',
            'description' => '用于验证正式执行成员工作台。',
            'payment_account_id' => 'PAY002',
            'budget' => 5000,
            'final_amount' => 4500,
            'expected_delivery' => now()->addDays(10)->toDateString(),
            'final_delivery' => now()->addDays(6)->toDateString(),
            'bidding_deadline' => null,
            'status' => 'ASSIGNED',
            'assignment_type' => 'DIRECT',
            'complexity' => 'HIGH',
            'is_urgent' => true,
            'created_by' => 'E30001',
            'primary_assignee_id' => 'E20001',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    DB::table('bid')->insert([
        'id' => 32001,
        'task_id' => 31001,
        'amount' => 2800,
        'delivery_date' => now()->addDays(7)->toDateString(),
        'proposal' => '当前用户参与的联合投标方案',
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => '31001:E20001',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('bid_member')->insert([
        ['id' => 32101, 'bid_id' => 32001, 'user_id' => 'E20001', 'role' => 'OWNER', 'created_at' => now()],
        ['id' => 32102, 'bid_id' => 32001, 'user_id' => 'E10001', 'role' => 'COLLABORATOR', 'created_at' => now()],
    ]);

    DB::table('task_assignee')->insert([
        ['id' => 33001, 'task_id' => 31002, 'user_id' => 'E20001', 'role' => 'OWNER', 'created_at' => now()],
        ['id' => 33002, 'task_id' => 31002, 'user_id' => 'E10001', 'role' => 'COLLABORATOR', 'created_at' => now()],
    ]);

    $session = [
        CurrentUserService::SESSION_KEY => ['employeeNo' => 'E10001', 'displayName' => '当前用户'],
        CurrentUserService::ROLE_SESSION_KEY => [],
        CurrentUserService::TOKEN_SESSION_KEY => [
            'accessToken' => 'token-personal-pages',
            'expiresAt' => now()->addHour()->toISOString(),
        ],
    ];
    $headers = [
        'X-Inertia' => 'true',
        'X-Inertia-Version' => inertiaVersionForTest(),
    ];

    $this->withSession($session)
        ->withHeaders($headers)
        ->get('/my-tasks/published?status=OPEN&keyword='.urlencode('接口'))
        ->assertOk()
        ->assertJsonPath('component', 'MyTasks/Published')
        ->assertJsonPath('props.tasks.meta.total', 1)
        ->assertJsonPath('props.tasks.data.0.id', '31001')
        ->assertJsonPath('props.tasks.data.0.bidCount', 1);

    $this->withSession($session)
        ->withHeaders($headers)
        ->get('/my-tasks/bids?status=ACTIVE&keyword='.urlencode('联合'))
        ->assertOk()
        ->assertJsonPath('component', 'MyTasks/Bids')
        ->assertJsonPath('props.bids.meta.total', 1)
        ->assertJsonPath('props.bids.data.0.role', 'COLLABORATOR')
        ->assertJsonPath('props.bids.data.0.members.0.displayName', '任务负责人');

    $this->withSession($session)
        ->withHeaders($headers)
        ->get('/my-tasks/assignments?status=ASSIGNED&keyword='.urlencode('报表'))
        ->assertOk()
        ->assertJsonPath('component', 'MyTasks/Assignments')
        ->assertJsonPath('props.assignments.meta.total', 1)
        ->assertJsonPath('props.assignments.data.0.role', 'COLLABORATOR')
        ->assertJsonPath('props.assignments.data.0.amountLabel', '¥4,500.00');
});

test('task return navigation only accepts known internal list pages', function (): void {
    $navigation = app(TaskReturnNavigation::class);

    expect($navigation->resolve('/my-tasks/published?status=OPEN&page=3'))
        ->toMatchArray([
            'activeNav' => 'my-tasks',
            'backLabel' => '返回我发布的',
            'backUrl' => '/my-tasks/published?status=OPEN&page=3',
        ])
        ->and($navigation->safeUrl('https://evil.example.test/tasks'))->toBeNull()
        ->and($navigation->safeUrl('//evil.example.test/tasks'))->toBeNull()
        ->and($navigation->safeUrl('/\\evil.example.test'))->toBeNull()
        ->and($navigation->safeUrl('/unknown'))->toBeNull()
        ->and($navigation->resolve('/unknown')['backUrl'])->toBe('/tasks');
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
    Schema::dropIfExists('task_assignee');
    Schema::dropIfExists('attachment_ref');
    Schema::dropIfExists('bid_member');
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
        // 是否紧急与复杂度独立，默认 false 兼容普通历史任务。
        $table->boolean('is_urgent')->default(false);
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

    Schema::create('bid_member', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('bid_id');
        $table->string('user_id', 32);
        $table->string('role', 20);
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('task_assignee', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->string('user_id', 32);
        $table->string('role', 20);
        $table->timestamp('created_at')->nullable();
    });

    Schema::create('attachment_ref', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('owner_type', 30);
        $table->unsignedBigInteger('owner_id');
        $table->string('attachment_id', 128);
        $table->string('attachment_name', 255);
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

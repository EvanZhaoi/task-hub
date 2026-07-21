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
    $this->withoutVite();

    $this->get('/')->assertOk();
});

test('protected task pages redirect to sso login when session is missing', function (): void {
    $this->get('/tasks')->assertRedirect('/login');
});

test('the sso callback page responds successfully', function (): void {
    $this->withoutVite();

    $this->get('/sso/callback')->assertOk();
});

test('authenticated users can view the task hall with filters', function (): void {
    $this->withoutVite();
    createTaskHallTables();

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

    $this->withSession([
        CurrentUserService::SESSION_KEY => [
            'employeeNo' => 'E10002',
            'displayName' => '李雷',
        ],
        CurrentUserService::ROLE_SESSION_KEY => ['TOP'],
    ])
        ->withHeader('X-Inertia', 'true')
        ->withHeader('X-Inertia-Version', inertiaVersionForTest())
        ->get('/tasks?status=PENDING_SELECTION&complexity=MEDIUM&keyword=%E7%99%BB%E5%BD%95')
        ->assertOk()
        ->assertJsonPath('component', 'Tasks/Index')
        ->assertJsonPath('props.filters.status', 'PENDING_SELECTION')
        ->assertJsonPath('props.filters.complexity', 'MEDIUM')
        ->assertJsonPath('props.filters.keyword', '登录')
        ->assertJsonPath('props.tasks.data.0.displayStatus', 'PENDING_SELECTION')
        ->assertJsonPath('props.tasks.data.0.activeBidCount', 1);
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

            return ['TOP'];
        }
    });

    $this->postJson('/sso/session', [
        'accessToken' => 'token-123',
    ])
        ->assertOk()
        ->assertJson([
            'redirectTo' => route('tasks.index'),
            'roles' => ['TOP'],
        ]);

    expect(session(CurrentUserService::SESSION_KEY)['employeeNo'])->toBe('E10001')
        ->and(session(CurrentUserService::ROLE_SESSION_KEY))->toBe(['TOP']);
});

test('logout clears sso session and redirects home', function (): void {
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
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing('taskhub.session_marker');
});

test('logout redirects to sso logout url when configured', function (): void {
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
        ->assertSessionMissing(CurrentUserService::SESSION_KEY)
        ->assertSessionMissing(CurrentUserService::ROLE_SESSION_KEY)
        ->assertSessionMissing('taskhub.session_marker');
});

test('sso user info path must not be a full url', function (): void {
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
    config([
        'sso.base_url' => 'https://sso.example.test',
        'sso.client_id' => 'ClientID',
        'sso.client_secret' => 'secret',
        'sso.userinfo_path' => '/api/current-user',
        'sso.timeout' => 3,
        'sso.verify_ssl' => false,
    ]);

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

    Http::assertSent(fn ($request): bool => $request->method() === 'POST'
        && $request->url() === 'https://sso.example.test/api/current-user'
        && $request->data()['clientId'] === 'ClientID'
        && $request->data()['secret'] === 'secret'
        && $request->data()['accessToken'] === 'token-123');
});

test('sso user parses nested user payload', function (): void {
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
    expect((new Task)->getTable())->toBe('task');
});

test('taskhub user role model maps to the existing role table', function (): void {
    expect((new TaskhubUserRole)->getTable())->toBe('taskhub_user_role');
});

function createTaskHallTables(): void
{
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
}

function inertiaVersionForTest(): string
{
    $manifest = public_path('build/manifest.json');

    return file_exists($manifest) ? hash_file('xxh128', $manifest) : '';
}

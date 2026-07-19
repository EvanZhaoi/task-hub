<?php

use App\Integrations\Sso\SsoClient;
use App\Integrations\Sso\SsoException;
use App\Integrations\Sso\SsoUser;
use App\Models\Task;
use App\Models\TaskhubUserRole;
use App\Services\CurrentUserService;
use App\Services\TaskhubRoleService;
use Illuminate\Support\Facades\Http;

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

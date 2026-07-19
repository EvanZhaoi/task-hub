<?php

use App\Models\Task;
use App\Models\TaskhubUserRole;

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

test('task model maps to the existing task table', function (): void {
    expect((new Task)->getTable())->toBe('task');
});

test('taskhub user role model maps to the existing role table', function (): void {
    expect((new TaskhubUserRole)->getTable())->toBe('taskhub_user_role');
});

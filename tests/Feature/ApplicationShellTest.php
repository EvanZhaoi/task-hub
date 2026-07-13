<?php

use App\Models\Task;

test('the inertia application shell responds successfully', function (): void {
    $this->withoutVite();

    $this->get('/')->assertOk();
});

test('task model maps to the existing task table', function (): void {
    expect((new Task())->getTable())->toBe('task');
});

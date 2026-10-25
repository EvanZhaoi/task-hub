<?php

use App\Models\AttachmentRef;
use App\Models\Bid;
use App\Models\BidMember;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskChangeRequest;
use App\Models\TaskDelivery;
use App\Models\TaskEvent;
use App\Models\TaskhubUserRole;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    // 测试表故意只创建 schema.sql 中真实存在的时间字段。
    // 如果 Model 再次尝试写不存在的 updated_at，SQLite 会直接抛出 SQL 错误。
    Schema::dropIfExists('task_event');
    Schema::dropIfExists('attachment_ref');
    Schema::dropIfExists('task_assignee');
    Schema::dropIfExists('bid_member');

    Schema::create('bid_member', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('bid_id');
        $table->string('user_id', 32);
        $table->string('role', 20);
        $table->timestamp('created_at')->useCurrent();
    });

    Schema::create('task_assignee', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->string('user_id', 32);
        $table->string('role', 20);
        $table->timestamp('created_at')->useCurrent();
    });

    Schema::create('attachment_ref', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('owner_type', 30);
        $table->unsignedBigInteger('owner_id');
        $table->string('attachment_id', 128);
        $table->string('attachment_name', 255);
        $table->string('uploaded_by', 32);
        $table->timestamp('created_at')->useCurrent();
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
        $table->timestamp('created_at')->useCurrent();
    });
});

afterEach(function (): void {
    Schema::dropIfExists('task_event');
    Schema::dropIfExists('attachment_ref');
    Schema::dropIfExists('task_assignee');
    Schema::dropIfExists('bid_member');
});

test('eloquent model timestamp columns match database schema', function (): void {
    // 这些业务主表实际同时存在 created_at 和 updated_at，应保留 Laravel 默认行为。
    $modelsWithBothTimestamps = [
        new Task,
        new Bid,
        new TaskDelivery,
        new TaskChangeRequest,
        new TaskhubUserRole,
    ];

    foreach ($modelsWithBothTimestamps as $model) {
        expect($model->usesTimestamps())->toBeTrue()
            ->and($model->getCreatedAtColumn())->toBe('created_at')
            ->and($model->getUpdatedAtColumn())->toBe('updated_at');
    }

    // 这些追加型表只有 created_at：继续自动写创建时间，但绝不能生成 updated_at。
    $modelsWithCreatedAtOnly = [
        new BidMember,
        new TaskAssignee,
        new AttachmentRef,
        new TaskEvent,
    ];

    foreach ($modelsWithCreatedAtOnly as $model) {
        expect($model->usesTimestamps())->toBeTrue()
            ->and($model->getCreatedAtColumn())->toBe('created_at')
            ->and($model->getUpdatedAtColumn())->toBeNull();
    }
});

test('created-at-only models insert records without updated_at column', function (): void {
    $insertQueries = [];

    // 记录真实生成的 SQL，除了依靠数据库报错，还明确断言 SQL 中不存在 updated_at。
    DB::listen(function ($query) use (&$insertQueries): void {
        if (str_starts_with(strtolower($query->sql), 'insert')) {
            $insertQueries[] = strtolower($query->sql);
        }
    });

    $bidMember = BidMember::query()->create([
        'id' => 1001,
        'bid_id' => 2001,
        'user_id' => 'E10001',
        'role' => 'OWNER',
    ]);

    $taskAssignee = TaskAssignee::query()->create([
        'id' => 1002,
        'task_id' => 3001,
        'user_id' => 'E10001',
        'role' => 'OWNER',
    ]);

    $attachment = AttachmentRef::query()->create([
        'id' => 1003,
        'owner_type' => 'TASK',
        'owner_id' => 3001,
        'attachment_id' => 'FILE-001',
        'attachment_name' => '需求说明.pdf',
        'uploaded_by' => 'E10001',
    ]);

    $event = TaskEvent::query()->create([
        'id' => 1004,
        'task_id' => 3001,
        'event_type' => 'TASK_CREATED',
        'operator_id' => 'E10001',
        'from_status' => null,
        'to_status' => 'OPEN',
        'event_data' => ['source' => 'timestamp-test'],
        'remark' => '验证仅创建时间模型',
    ]);

    expect($bidMember->created_at)->not->toBeNull()
        ->and($taskAssignee->created_at)->not->toBeNull()
        ->and($attachment->created_at)->not->toBeNull()
        ->and($event->created_at)->not->toBeNull()
        ->and($insertQueries)->toHaveCount(4);

    foreach ($insertQueries as $sql) {
        expect($sql)->not->toContain('updated_at');
    }
});

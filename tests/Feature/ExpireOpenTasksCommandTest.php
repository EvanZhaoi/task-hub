<?php

use App\Mail\BiddingSelectionRequiredMail;
use App\Mail\TaskFailedNoBidMail;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    createDeadlineTaskTables();
    Mail::fake();
});

afterEach(function (): void {
    Schema::dropIfExists('task_event');
    Schema::dropIfExists('bid');
    Schema::dropIfExists('task');
});

test('expired open task without active bids is failed and publisher is notified', function (): void {
    insertDeadlineTask(41001, '无投标自动流标', 'OPEN');

    $this->artisan('taskhub:expire-open-tasks')
        ->expectsOutputToContain('自动流标 1')
        ->assertSuccessful();

    $task = DB::table('task')->where('id', 41001)->first();
    $event = DB::table('task_event')->where('task_id', 41001)->first();

    expect($task)->toMatchObject([
        'status' => 'FAILED',
        'version' => 1,
    ])->and($event)->not->toBeNull()
        ->and($event->event_type)->toBe('TASK_FAILED')
        ->and(json_decode($event->event_data, true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'failureType' => 'NO_BID_TIMEOUT',
            'bidCount' => 0,
        ]);

    Mail::assertSent(TaskFailedNoBidMail::class, function (TaskFailedNoBidMail $mail): bool {
        return $mail->hasTo('publisher@example.test')
            && $mail->taskTitle === '无投标自动流标'
            && $mail->reason === '招标截止时没有有效投标';
    });
});

test('expired open task with active bid stays open and selection reminder is sent once', function (): void {
    insertDeadlineTask(41002, '截止后等待选标', 'OPEN');
    insertActiveBid(42001, 41002);

    $this->artisan('taskhub:expire-open-tasks')
        ->expectsOutputToContain('待选标 1')
        ->assertSuccessful();

    $this->artisan('taskhub:expire-open-tasks')
        ->expectsOutputToContain('待选标 0')
        ->assertSuccessful();

    $task = DB::table('task')->where('id', 41002)->first();
    $events = DB::table('task_event')
        ->where('task_id', 41002)
        ->where('event_type', 'BIDDING_DEADLINE_REACHED')
        ->get();

    expect($task)->toMatchObject([
        'status' => 'OPEN',
        'version' => 0,
    ])->and($events)->toHaveCount(1)
        ->and(json_decode($events->first()->event_data, true, flags: JSON_THROW_ON_ERROR))->toMatchArray([
            'activeBidCount' => 1,
            'notificationType' => 'SELECTION_REQUIRED',
        ]);

    Mail::assertSent(BiddingSelectionRequiredMail::class, 1);
    Mail::assertSent(BiddingSelectionRequiredMail::class, function (BiddingSelectionRequiredMail $mail): bool {
        return $mail->hasTo('publisher@example.test')
            && $mail->taskTitle === '截止后等待选标'
            && $mail->activeBidCount === 1;
    });
});

test('assigned task past bidding deadline is ignored without event or email', function (): void {
    insertDeadlineTask(41003, '已经完成选标', 'ASSIGNED');

    $this->artisan('taskhub:expire-open-tasks')
        ->expectsOutputToContain('扫描 0')
        ->assertSuccessful();

    expect(DB::table('task')->where('id', 41003)->first())->toMatchObject([
        'status' => 'ASSIGNED',
        'version' => 0,
    ])->and(DB::table('task_event')->where('task_id', 41003)->exists())->toBeFalse();

    Mail::assertNothingSent();
});

test('deadline command continues past the first batch of one hundred tasks', function (): void {
    $tasks = [];
    $now = now();

    for ($offset = 0; $offset < 105; $offset++) {
        $tasks[] = deadlineTaskRow(
            id: 41100 + $offset,
            title: "批量截止任务 {$offset}",
            status: 'OPEN',
            now: $now,
        );
    }

    DB::table('task')->insert($tasks);

    $this->artisan('taskhub:expire-open-tasks')
        ->expectsOutputToContain('扫描 105')
        ->assertSuccessful();

    expect(DB::table('task')->where('status', 'FAILED')->count())->toBe(105)
        ->and(DB::table('task_event')->where('event_type', 'TASK_FAILED')->count())->toBe(105);

    Mail::assertSent(TaskFailedNoBidMail::class, 105);
});

/**
 * 创建截止任务测试需要的最小 SQLite 表结构。
 *
 * 字段名称和时间戳行为与 database/schema.sql 保持一致，测试不会运行默认 Migration。
 */
function createDeadlineTaskTables(): void
{
    Schema::dropIfExists('task_event');
    Schema::dropIfExists('bid');
    Schema::dropIfExists('task');

    Schema::create('task', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->string('title', 200);
        $table->string('assignment_type', 20);
        $table->string('status', 20);
        $table->timestamp('bidding_deadline')->nullable();
        $table->string('created_by', 32);
        $table->json('created_by_snapshot')->nullable();
        $table->unsignedBigInteger('version')->default(0);
        $table->timestamps();
    });

    Schema::create('bid', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->decimal('amount', 18, 2);
        $table->date('delivery_date');
        $table->string('status', 20);
        $table->integer('revision_no')->default(1);
        $table->string('active_key', 160)->nullable();
        $table->timestamp('withdrawn_at')->nullable();
        $table->timestamps();
    });

    Schema::create('task_event', function (Blueprint $table): void {
        $table->unsignedBigInteger('id')->primary();
        $table->unsignedBigInteger('task_id');
        $table->string('event_type', 40);
        $table->string('operator_id', 32)->nullable();
        $table->string('operator_name', 100)->nullable();
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
 * 插入一条已超过招标截止时间的测试任务。
 */
function insertDeadlineTask(int $id, string $title, string $status): void
{
    DB::table('task')->insert(deadlineTaskRow($id, $title, $status, now()));
}

/**
 * 构造一条截止任务数据库行，供单条和批量测试复用。
 *
 * @return array<string, mixed>
 */
function deadlineTaskRow(int $id, string $title, string $status, mixed $now): array
{
    return [
        'id' => $id,
        'title' => $title,
        'assignment_type' => 'BIDDING',
        'status' => $status,
        'bidding_deadline' => $now->copy()->subMinute(),
        'created_by' => 'E10001',
        'created_by_snapshot' => json_encode([
            'displayName' => '任务发布者',
            'email' => 'publisher@example.test',
        ], JSON_THROW_ON_ERROR),
        'version' => 0,
        'created_at' => $now->copy()->subDay(),
        'updated_at' => $now,
    ];
}

/**
 * 为指定任务插入一条 ACTIVE 投标。
 */
function insertActiveBid(int $id, int $taskId): void
{
    DB::table('bid')->insert([
        'id' => $id,
        'task_id' => $taskId,
        'amount' => 1800,
        'delivery_date' => now()->addDays(5)->toDateString(),
        'status' => 'ACTIVE',
        'revision_no' => 1,
        'active_key' => "{$taskId}:E20001",
        'created_at' => now()->subHour(),
        'updated_at' => now(),
    ]);
}

<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 发布者确认完成任务的业务服务。
 *
 * Phase 1 暂未开放正式交付验收，因此由发布者对 ASSIGNED 任务执行一次人工完成确认。
 * 第二期接入 TaskDelivery 后，任务完成入口将改为交付验收，但历史事件仍可通过 completionMode 区分。
 */
class CompleteTaskService
{
    /**
     * 注入业务 ID 生成器和事务提交后使用的邮件通知服务。
     */
    public function __construct(
        private readonly SnowflakeId $ids,
        private readonly TaskEmailNotificationService $emailNotifications,
    ) {}

    /**
     * 执行完整的任务完结流程。
     *
     * @throws ValidationException 当操作者、状态、版本或执行成员不符合规则时抛出中文业务错误。
     */
    public function execute(
        Task $task,
        SsoUser $user,
        int $baseVersion,
        string $accessToken,
    ): Task {
        $completedTask = DB::transaction(function () use ($baseVersion, $task, $user): Task {
            // 锁定任务后重新校验，避免完成动作与其它状态更新并发覆盖。
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanComplete($lockedTask, $user->employeeNo(), $baseVersion);

            $completedAt = now();

            $lockedTask->forceFill([
                'status' => 'COMPLETED',
                'completed_at' => $completedAt,
                'updated_by' => $user->employeeNo(),
                'version' => $lockedTask->version + 1,
            ])->save();

            TaskEvent::query()->create([
                'id' => $this->ids->next(),
                'task_id' => $lockedTask->id,
                'event_type' => 'TASK_COMPLETED',
                'operator_id' => $user->employeeNo(),
                'operator_name' => $user->displayName(),
                'from_status' => 'ASSIGNED',
                'to_status' => 'COMPLETED',
                'related_type' => null,
                'related_id' => null,
                'event_data' => [
                    // 第二期可使用 DELIVERY_ACCEPTED，并附带 deliveryId。
                    'completionMode' => 'MANUAL_PHASE_ONE',
                    'completedAt' => $completedAt->toISOString(),
                ],
                'remark' => '发布者确认任务完成',
            ]);

            return $lockedTask->refresh();
        });

        // 邮件在事务成功提交后发送；外部人员接口或邮件失败不能回滚任务状态。
        $members = TaskAssignee::query()
            ->where('task_id', $completedTask->id)
            ->orderByRaw("role = 'OWNER' DESC")
            ->orderBy('id')
            ->get(['user_id', 'role'])
            ->map(fn (TaskAssignee $member): array => [
                'employeeNo' => (string) $member->user_id,
                'role' => (string) $member->role,
            ])
            ->all();

        $this->emailNotifications->notifyTaskCompleted(
            task: $completedTask,
            members: $members,
            accessToken: $accessToken,
        );

        return $completedTask;
    }

    /**
     * 校验当前任务是否允许由当前用户确认完成。
     */
    private function assertCanComplete(Task $task, string $employeeNo, int $baseVersion): void
    {
        if ((string) $task->created_by !== $employeeNo) {
            throw ValidationException::withMessages([
                'task' => '只有任务发布者可以确认任务完成。',
            ]);
        }

        if ($task->status !== 'ASSIGNED') {
            throw ValidationException::withMessages([
                'task' => '只有进行中的任务可以确认完成。',
            ]);
        }

        if ($task->version !== $baseVersion) {
            throw ValidationException::withMessages([
                'version' => '任务数据已经发生变化，请刷新页面后重试。',
            ]);
        }

        if (! TaskAssignee::query()->where('task_id', $task->id)->exists()) {
            throw ValidationException::withMessages([
                'task' => '任务没有正式执行成员，暂时不能确认完成。',
            ]);
        }
    }
}

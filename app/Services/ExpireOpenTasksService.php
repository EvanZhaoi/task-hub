<?php

namespace App\Services;

use App\Models\Bid;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;

/**
 * 处理单个已到招标截止时间的 OPEN 任务。
 *
 * 本服务只处理一个任务，确保每个任务使用独立短事务：
 * 1. 已选标或状态已变化时跳过。
 * 2. 没有 ACTIVE 投标时自动流标。
 * 3. 存在 ACTIVE 投标时保持 OPEN，并记录一次待选标截止事件。
 * 4. 事务提交后再发送邮件，邮件异常不能回滚业务状态。
 */
class ExpireOpenTasksService
{
    public const OUTCOME_SKIPPED = 'SKIPPED';

    public const OUTCOME_ALREADY_PROCESSED = 'ALREADY_PROCESSED';

    public const OUTCOME_SELECTION_REQUIRED = 'SELECTION_REQUIRED';

    public const OUTCOME_FAILED_NO_BID = 'FAILED_NO_BID';

    /**
     * 注入事件 ID 生成器和邮件通知服务。
     */
    public function __construct(
        private readonly SnowflakeId $ids,
        private readonly TaskEmailNotificationService $emailNotifications,
    ) {}

    /**
     * 处理一个候选任务并返回处理结果。
     *
     * Task 使用行锁串行化投标、撤回、选标和截止处理。即使命令扫描后任务刚被选标，
     * 锁内也会读到 ASSIGNED 并跳过，不会错误改成 FAILED 或发送截止邮件。
     */
    public function execute(int|string $taskId): string
    {
        /** @var array{outcome: string, task: Task|null, activeBidCount: int} $result */
        $result = DB::transaction(function () use ($taskId): array {
            $task = Task::query()
                ->whereKey($taskId)
                ->lockForUpdate()
                ->first();

            if ($task === null || ! $this->isExpiredOpenBiddingTask($task)) {
                return $this->result(self::OUTCOME_SKIPPED);
            }

            $activeBidCount = Bid::query()
                ->where('task_id', $task->id)
                ->where('status', 'ACTIVE')
                ->count();

            if ($activeBidCount === 0) {
                return $this->failTaskWithoutBid($task);
            }

            return $this->markSelectionRequired($task, $activeBidCount);
        });

        // 邮件必须在数据库事务提交后发送；SMTP 失败由通知服务记录，不回滚任务和事件。
        if ($result['task'] instanceof Task) {
            if ($result['outcome'] === self::OUTCOME_SELECTION_REQUIRED) {
                $this->emailNotifications->notifyBiddingSelectionRequired(
                    $result['task'],
                    $result['activeBidCount'],
                );
            }

            if ($result['outcome'] === self::OUTCOME_FAILED_NO_BID) {
                $this->emailNotifications->notifyNoBidFailure($result['task']);
            }
        }

        return $result['outcome'];
    }

    /**
     * 判断锁定后的任务是否仍属于本服务的处理范围。
     *
     * DIRECT 任务没有招标截止时间；ASSIGNED、COMPLETED、FAILED、CANCELLED 等状态全部跳过。
     */
    private function isExpiredOpenBiddingTask(Task $task): bool
    {
        return $task->assignment_type === 'BIDDING'
            && $task->status === 'OPEN'
            && $task->bidding_deadline !== null
            && $task->bidding_deadline->isPast();
    }

    /**
     * 将没有 ACTIVE 投标的任务更新为自动流标。
     *
     * Task 当前状态发生变化，因此递增 version，并写入 TASK_FAILED 事件保存失败方式和原因。
     *
     * @return array{outcome: string, task: Task, activeBidCount: int}
     */
    private function failTaskWithoutBid(Task $task): array
    {
        $task->forceFill([
            'status' => 'FAILED',
            'version' => $task->version + 1,
        ])->save();

        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_FAILED',
            'operator_id' => null,
            'from_status' => 'OPEN',
            'to_status' => 'FAILED',
            'related_type' => null,
            'related_id' => null,
            'event_data' => [
                'failureType' => 'NO_BID_TIMEOUT',
                'reason' => '招标截止时没有有效投标',
                'bidCount' => 0,
                'biddingDeadline' => $this->deadlineKey($task),
            ],
            'remark' => '招标截止时没有有效投标，任务自动流标',
        ]);

        return $this->result(self::OUTCOME_FAILED_NO_BID, $task->refresh());
    }

    /**
     * 为存在 ACTIVE 投标的截止任务记录待选标事件。
     *
     * Task 继续保持 OPEN，“待选标”只是页面派生状态。事件按当前 bidding_deadline 去重，
     * 防止每分钟扫描都重复写事件和发送邮件；未来延长截止时间后，新截止时间可以再次提醒。
     *
     * @return array{outcome: string, task: Task|null, activeBidCount: int}
     */
    private function markSelectionRequired(Task $task, int $activeBidCount): array
    {
        $deadline = $this->deadlineKey($task);

        $alreadyRecorded = TaskEvent::query()
            ->where('task_id', $task->id)
            ->where('event_type', 'BIDDING_DEADLINE_REACHED')
            ->get(['event_data'])
            ->contains(fn (TaskEvent $event): bool => ($event->event_data['biddingDeadline'] ?? null) === $deadline);

        if ($alreadyRecorded) {
            return $this->result(self::OUTCOME_ALREADY_PROCESSED);
        }

        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'BIDDING_DEADLINE_REACHED',
            'operator_id' => null,
            'from_status' => 'OPEN',
            'to_status' => 'OPEN',
            'related_type' => null,
            'related_id' => null,
            'event_data' => [
                'biddingDeadline' => $deadline,
                'activeBidCount' => $activeBidCount,
                'notificationType' => 'SELECTION_REQUIRED',
            ],
            'remark' => '招标已截止，等待任务发布者选标',
        ]);

        return $this->result(self::OUTCOME_SELECTION_REQUIRED, $task, $activeBidCount);
    }

    /**
     * 将截止时间转换成稳定的事件去重键。
     *
     * 数据库时间精度为秒；使用固定格式可以避免 Carbon 序列化时区格式差异导致重复提醒。
     */
    private function deadlineKey(Task $task): string
    {
        return $task->bidding_deadline?->format('Y-m-d H:i:s') ?? '';
    }

    /**
     * 统一构造事务返回结果，避免不同分支遗漏字段。
     *
     * @return array{outcome: string, task: Task|null, activeBidCount: int}
     */
    private function result(string $outcome, ?Task $task = null, int $activeBidCount = 0): array
    {
        return [
            'outcome' => $outcome,
            'task' => $task,
            'activeBidCount' => $activeBidCount,
        ];
    }
}

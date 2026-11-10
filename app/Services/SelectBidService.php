<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\Bid;
use App\Models\BidMember;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 发布者选定中标投标的业务服务。
 *
 * 选标会同时修改 Task、多条 Bid、TaskAssignee 和 TaskEvent，必须作为一个原子业务动作处理。
 * 任一步失败时，整个数据库事务都会回滚，不能出现任务已指派但成员或事件缺失的状态。
 */
class SelectBidService
{
    /**
     * 注入业务 ID 生成器。
     *
     * TaskAssignee 和 TaskEvent 都使用项目统一的业务 ID，不依赖数据库自增。
     */
    public function __construct(
        private readonly SnowflakeId $ids,
        private readonly TaskEmailNotificationService $emailNotifications,
    ) {}

    /**
     * 执行完整选标事务。
     *
     * @throws ValidationException 当操作者、任务状态、投标状态或成员结构不满足选标规则时抛出中文业务错误。
     */
    public function execute(Task $task, Bid $bid, SsoUser $user, string $accessToken): Task
    {
        $assignedTask = DB::transaction(function () use ($bid, $task, $user): Task {
            // 先锁定任务，串行化同一任务上的并发选标、投标和后续状态变更。
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertTaskCanSelectBid($lockedTask, $user->employeeNo());

            // 目标 Bid 也需要锁定，并在锁内重新校验归属和状态，不能信任路由绑定时读取的旧对象。
            $lockedBid = Bid::query()
                ->whereKey($bid->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertBidCanBeSelected($lockedTask, $lockedBid);

            $members = BidMember::query()
                ->where('bid_id', $lockedBid->id)
                ->orderByRaw("role = 'OWNER' DESC")
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $owner = $members->where('role', 'OWNER');

            if ($owner->count() !== 1) {
                throw ValidationException::withMessages([
                    'bid' => '目标投标的主投标人数据不完整，暂时无法选标。',
                ]);
            }

            if ($members->isEmpty()) {
                throw ValidationException::withMessages([
                    'bid' => '目标投标没有有效成员，暂时无法选标。',
                ]);
            }

            if (TaskAssignee::query()->where('task_id', $lockedTask->id)->exists()) {
                throw ValidationException::withMessages([
                    'bid' => '任务已经存在执行成员，不能重复选标。',
                ]);
            }

            $ownerEmployeeNo = (string) $owner->first()->user_id;

            $this->acceptTargetBid($lockedBid);
            $this->loseOtherActiveBids($lockedTask, $lockedBid);
            $this->assignTask($lockedTask, $lockedBid, $ownerEmployeeNo, $user->employeeNo());
            $this->copyMembersToAssignees($lockedTask, $members->all());
            $this->createSelectionEvents(
                $lockedTask,
                $lockedBid,
                $ownerEmployeeNo,
                $members->count(),
                $user->employeeNo(),
                $user->displayName(),
            );

            return $lockedTask->refresh();
        });

        // 事务提交后重新读取中标成员，分别发送负责人/协作成员通知。
        $members = BidMember::query()
            ->where('bid_id', $bid->id)
            ->orderByRaw("role = 'OWNER' DESC")
            ->orderBy('id')
            ->get(['user_id', 'role'])
            ->map(fn (BidMember $member): array => [
                'employeeNo' => (string) $member->user_id,
                'role' => (string) $member->role,
            ])
            ->all();

        $this->emailNotifications->notifyBidSelected(
            task: $assignedTask,
            bid: $bid->refresh(),
            members: $members,
            accessToken: $accessToken,
        );

        return $assignedTask;
    }

    /**
     * 校验任务是否允许当前用户执行选标。
     */
    private function assertTaskCanSelectBid(Task $task, string $employeeNo): void
    {
        if ($task->created_by !== $employeeNo) {
            throw ValidationException::withMessages([
                'bid' => '只有任务发布者可以选择中标方案。',
            ]);
        }

        if ($task->assignment_type !== 'BIDDING') {
            throw ValidationException::withMessages([
                'bid' => '直接指派任务不需要进行选标。',
            ]);
        }

        if ($task->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'bid' => '当前任务不在招标中，不能进行选标。',
            ]);
        }
    }

    /**
     * 校验目标投标属于当前任务且仍为 ACTIVE。
     */
    private function assertBidCanBeSelected(Task $task, Bid $bid): void
    {
        if ((string) $bid->task_id !== (string) $task->id) {
            throw ValidationException::withMessages([
                'bid' => '目标投标不属于当前任务。',
            ]);
        }

        if ($bid->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'bid' => '目标投标已失效，不能重复选标。',
            ]);
        }
    }

    /**
     * 将目标投标标记为中标，并释放 ACTIVE 唯一键。
     */
    private function acceptTargetBid(Bid $bid): void
    {
        $bid->forceFill([
            'status' => 'ACCEPTED',
            'active_key' => null,
        ])->save();
    }

    /**
     * 将同一任务的其他 ACTIVE 投标统一标记为未中标。
     */
    private function loseOtherActiveBids(Task $task, Bid $acceptedBid): void
    {
        Bid::query()
            ->where('task_id', $task->id)
            ->where('id', '!=', $acceptedBid->id)
            ->where('status', 'ACTIVE')
            ->update([
                'status' => 'LOST',
                'active_key' => null,
            ]);
    }

    /**
     * 使用中标方案更新任务当前状态、负责人、最终金额和最终交期。
     */
    private function assignTask(
        Task $task,
        Bid $bid,
        string $ownerEmployeeNo,
        string $operatorEmployeeNo,
    ): void {
        $task->forceFill([
            'status' => 'ASSIGNED',
            'assigned_bid_id' => $bid->id,
            'primary_assignee_id' => $ownerEmployeeNo,
            'final_amount' => $bid->amount,
            'final_delivery' => $bid->delivery_date,
            'version' => $task->version + 1,
            'updated_by' => $operatorEmployeeNo,
        ])->save();
    }

    /**
     * 将中标 BidMember 按原角色复制为正式 TaskAssignee。
     *
     * @param  list<BidMember>  $members
     */
    private function copyMembersToAssignees(Task $task, array $members): void
    {
        $createdAt = now();

        TaskAssignee::query()->insert(array_map(
            fn (BidMember $member): array => [
                'id' => $this->ids->next(),
                'task_id' => $task->id,
                'user_id' => $member->user_id,
                'role' => $member->role,
                'created_at' => $createdAt,
            ],
            $members,
        ));
    }

    /**
     * 写入“投标中标”和“任务已指派”两个关键业务事件。
     */
    private function createSelectionEvents(
        Task $task,
        Bid $bid,
        string $ownerEmployeeNo,
        int $memberCount,
        string $operatorEmployeeNo,
        string $operatorName,
    ): void {
        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'BID_ACCEPTED',
            'operator_id' => $operatorEmployeeNo,
            'operator_name' => $operatorName,
            'from_status' => 'OPEN',
            'to_status' => 'OPEN',
            'related_type' => 'BID',
            'related_id' => $bid->id,
            'event_data' => [
                'amount' => $bid->amount,
                'deliveryDate' => $bid->delivery_date?->toDateString(),
                'ownerEmployeeNo' => $ownerEmployeeNo,
                'memberCount' => $memberCount,
            ],
            'remark' => '发布者选定中标方案',
        ]);

        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_ASSIGNED',
            'operator_id' => $operatorEmployeeNo,
            'operator_name' => $operatorName,
            'from_status' => 'OPEN',
            'to_status' => 'ASSIGNED',
            'related_type' => 'BID',
            'related_id' => $bid->id,
            'event_data' => [
                'primaryAssigneeId' => $ownerEmployeeNo,
                'memberCount' => $memberCount,
            ],
            'remark' => '任务已根据中标方案指派执行成员',
        ]);
    }
}

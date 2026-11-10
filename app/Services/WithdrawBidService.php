<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\Bid;
use App\Models\BidMember;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 撤回投标业务服务。
 *
 * 这个 Service 完整表达一次撤回动作：锁定最新数据、校验任务与投标、
 * 将 ACTIVE 投标改为 WITHDRAWN，并追加 BID_WITHDRAWN 审计事件。
 */
class WithdrawBidService
{
    /**
     * 注入业务主键生成器。
     *
     * 撤回不会创建新 Bid，但需要为 TaskEvent 生成符合现有表结构的 BIGINT 主键。
     */
    public function __construct(private readonly SnowflakeId $ids) {}

    /**
     * 执行撤回投标事务。
     *
     * 锁定顺序与选标流程保持一致：先锁 Task，再锁 Bid，降低并发操作形成死锁的风险。
     * 任务状态、截止时间和 OWNER 身份都在锁内重新检查，不能只依赖页面按钮是否显示。
     *
     * @throws ValidationException 当投标不属于任务、操作者不是 OWNER 或当前状态不允许撤回时抛出中文业务错误。
     */
    public function execute(Task $task, Bid $bid, SsoUser $user): Bid
    {
        return DB::transaction(function () use ($bid, $task, $user): Bid {
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedBid = Bid::query()
                ->whereKey($bid->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employeeNo = $user->employeeNo();

            $this->assertBidBelongsToTask($lockedTask, $lockedBid);
            $this->assertTaskAllowsWithdrawal($lockedTask);
            $this->assertBidAllowsWithdrawal($lockedBid);
            $this->assertUserIsOwner($lockedBid, $employeeNo);

            $withdrawnAt = now();

            // 清空 active_key 后，数据库唯一索引才允许同一 OWNER 再次提交新的 ACTIVE 投标。
            $lockedBid->forceFill([
                'status' => 'WITHDRAWN',
                'active_key' => null,
                'withdrawn_at' => $withdrawnAt,
            ])->save();

            $this->createWithdrawnEvent($lockedTask, $lockedBid, $employeeNo, $user->displayName());

            return $lockedBid->refresh();
        });
    }

    /**
     * 校验 URL 中的 Bid 确实属于 URL 中的 Task。
     *
     * 该检查防止用户替换路由参数，撤回其他任务中的投标记录。
     */
    private function assertBidBelongsToTask(Task $task, Bid $bid): void
    {
        if ((string) $bid->task_id !== (string) $task->id) {
            throw ValidationException::withMessages([
                'bid' => '目标投标不属于当前任务。',
            ]);
        }
    }

    /**
     * 校验任务当前仍允许撤回投标。
     *
     * 撤回只在 OPEN 且招标尚未截止时开放；已选标、流标或取消后都不能再改变投标历史。
     */
    private function assertTaskAllowsWithdrawal(Task $task): void
    {
        if ($task->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'bid' => '当前任务不在招标中，不能撤回投标。',
            ]);
        }

        if ($task->bidding_deadline === null || ! $task->bidding_deadline->isFuture()) {
            throw ValidationException::withMessages([
                'bid' => '招标已经截止，不能撤回投标。',
            ]);
        }
    }

    /**
     * 校验投标仍为有效状态。
     *
     * 已撤回、中标和未中标记录都是不可修改的业务历史，重复请求不会再次写入撤回事件。
     */
    private function assertBidAllowsWithdrawal(Bid $bid): void
    {
        if ($bid->status !== 'ACTIVE') {
            throw ValidationException::withMessages([
                'bid' => '该投标已经失效，不能重复撤回。',
            ]);
        }
    }

    /**
     * 校验当前用户是该投标唯一的 OWNER。
     *
     * COLLABORATOR 可以查看共同投标，但不能代表主投标人撤回整个方案。
     */
    private function assertUserIsOwner(Bid $bid, string $employeeNo): void
    {
        $isOwner = BidMember::query()
            ->where('bid_id', $bid->id)
            ->where('role', 'OWNER')
            ->where('user_id', $employeeNo)
            ->exists();

        if (! $isOwner) {
            throw ValidationException::withMessages([
                'bid' => '只有主投标人可以撤回该投标。',
            ]);
        }
    }

    /**
     * 追加投标撤回事件。
     *
     * Task 状态没有发生变化，因此 from_status 和 to_status 都保持 OPEN；
     * event_data 保存修订号和撤回时间，供详情时间线和后续审计使用。
     */
    private function createWithdrawnEvent(
        Task $task,
        Bid $bid,
        string $employeeNo,
        string $operatorName,
    ): void {
        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'BID_WITHDRAWN',
            'operator_id' => $employeeNo,
            'operator_name' => $operatorName,
            'from_status' => $task->status,
            'to_status' => $task->status,
            'related_type' => 'BID',
            'related_id' => $bid->id,
            'event_data' => [
                'revisionNo' => $bid->revision_no,
                'withdrawnAt' => $bid->withdrawn_at?->toISOString(),
            ],
            'remark' => '主投标人撤回投标',
        ]);
    }
}

<?php

namespace App\Services;

use App\Integrations\Sso\SsoUser;
use App\Models\AttachmentRef;
use App\Models\Bid;
use App\Models\BidMember;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 提交投标业务服务。
 *
 * Controller 只负责接收 HTTP 请求和返回响应。
 * 这个 Service 负责完整的“开发者对任务提交投标”业务动作：
 * 1. 校验任务是否仍允许投标。
 * 2. 校验当前用户是否已经存在 ACTIVE 投标。
 * 3. 在同一个事务中创建 Bid、BidMember、AttachmentRef 和 TaskEvent。
 */
class PlaceBidService
{
    /**
     * 创建投标服务。
     *
     * SnowflakeId 负责生成业务主键，避免在 Controller 或 Model 中散落 ID 生成逻辑。
     */
    public function __construct(
        private readonly SnowflakeId $ids,
    ) {}

    /**
     * 执行投标流程。
     *
     * @param  array<string, mixed>  $validated  StoreBidRequest 已校验通过的投标表单数据。
     * @param  list<array{id: string, name: string}>  $attachments  已上传到总部文件服务的附件引用。
     *
     * @throws ValidationException 当任务状态、截止时间或重复投标规则不满足时抛出字段错误。
     */
    public function execute(Task $task, array $validated, array $attachments, SsoUser $user): Bid
    {
        return DB::transaction(function () use ($attachments, $task, $user, $validated): Bid {
            // 锁定任务行，避免投标提交和选标、流标等状态变更同时发生时读到旧状态。
            $lockedTask = Task::query()
                ->whereKey($task->id)
                ->lockForUpdate()
                ->firstOrFail();

            $employeeNo = $user->employeeNo();

            $this->assertTaskCanReceiveBid($lockedTask, $employeeNo);

            $activeKey = $this->activeKey($lockedTask, $employeeNo);

            if ($this->hasActiveBid($lockedTask, $employeeNo)) {
                throw ValidationException::withMessages([
                    'amount' => '你已经对该任务提交过有效投标，请先撤回后再重新投标。',
                ]);
            }

            $bid = $this->createBid($lockedTask, $validated, $employeeNo, $activeKey);
            $this->createOwnerMember($bid, $employeeNo);
            $this->saveAttachments($bid, $attachments, $employeeNo);
            $this->createBidSubmittedEvent($lockedTask, $bid, $employeeNo, count($attachments));

            return $bid;
        });
    }

    /**
     * 校验任务是否允许当前用户投标。
     *
     * 这里处理真正影响数据合法性的业务规则：
     * 任务必须是 OPEN、必须未截止、发布者不能投自己的任务。
     */
    private function assertTaskCanReceiveBid(Task $task, string $employeeNo): void
    {
        if ($task->status !== 'OPEN') {
            throw ValidationException::withMessages([
                'amount' => '当前任务不在招标中，不能提交投标。',
            ]);
        }

        if ($task->created_by === $employeeNo) {
            throw ValidationException::withMessages([
                'amount' => '任务发布者不能投自己的任务。',
            ]);
        }

        if ($task->bidding_deadline === null || ! $task->bidding_deadline->isFuture()) {
            throw ValidationException::withMessages([
                'amount' => '招标已截止，不能继续提交投标。',
            ]);
        }
    }

    /**
     * 判断当前 OWNER 是否已有 ACTIVE 投标。
     *
     * 主投标人统一存在 bid_member 中，因此这里通过 whereHas('members') 查询 OWNER。
     */
    private function hasActiveBid(Task $task, string $employeeNo): bool
    {
        return Bid::query()
            ->where('task_id', $task->id)
            ->where('status', 'ACTIVE')
            ->whereHas('members', function ($query) use ($employeeNo): void {
                $query
                    ->where('role', 'OWNER')
                    ->where('user_id', $employeeNo);
            })
            ->exists();
    }

    /**
     * 生成 ACTIVE 投标唯一键。
     *
     * 数据库通过 bid.active_key 唯一索引限制同一任务同一 OWNER 只能有一条有效投标。
     */
    private function activeKey(Task $task, string $employeeNo): string
    {
        return "{$task->id}:{$employeeNo}";
    }

    /**
     * 创建 bid 主记录。
     *
     * revision_no 保留撤回重投历史；同一任务同一 OWNER 的第 N 次投标写入 N。
     *
     * @param  array<string, mixed>  $validated
     */
    private function createBid(Task $task, array $validated, string $employeeNo, string $activeKey): Bid
    {
        return Bid::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'amount' => $validated['amount'],
            'delivery_date' => $validated['deliveryDate'],
            'proposal' => $validated['proposal'] ?? null,
            'status' => 'ACTIVE',
            'revision_no' => $this->nextRevisionNo($task, $employeeNo),
            'active_key' => $activeKey,
        ]);
    }

    /**
     * 计算当前 OWNER 在该任务上的下一次投标修订号。
     *
     * 因为 bid 表不保存 bidder_id，所以要通过 bid_member 查找历史 OWNER 投标。
     */
    private function nextRevisionNo(Task $task, string $employeeNo): int
    {
        $maxRevisionNo = Bid::query()
            ->where('task_id', $task->id)
            ->whereHas('members', function ($query) use ($employeeNo): void {
                $query
                    ->where('role', 'OWNER')
                    ->where('user_id', $employeeNo);
            })
            ->max('revision_no');

        return ((int) $maxRevisionNo) + 1;
    }

    /**
     * 写入主投标人成员记录。
     *
     * 单人投标也必须写一条 OWNER 成员，后续选标时会从 bid_member 复制到 task_assignee。
     */
    private function createOwnerMember(Bid $bid, string $employeeNo): void
    {
        BidMember::query()->create([
            'id' => $this->ids->next(),
            'bid_id' => $bid->id,
            'user_id' => $employeeNo,
            'role' => 'OWNER',
        ]);
    }

    /**
     * 保存投标附件引用。
     *
     * TaskHub 只保存总部文件服务返回的附件 ID 和名称，不保存真实文件内容。
     *
     * @param  list<array{id: string, name: string}>  $attachments
     */
    private function saveAttachments(Bid $bid, array $attachments, string $employeeNo): void
    {
        if ($attachments === []) {
            return;
        }

        $now = now();

        AttachmentRef::query()->insert(array_map(
            fn (array $attachment): array => [
                'id' => $this->ids->next(),
                'owner_type' => 'BID',
                'owner_id' => $bid->id,
                'attachment_id' => $attachment['id'],
                'attachment_name' => $attachment['name'],
                'uploaded_by' => $employeeNo,
                'created_at' => $now,
            ],
            $attachments,
        ));
    }

    /**
     * 写入投标提交事件。
     *
     * TaskEvent 用于任务详情时间线和审计，不用于反向重建任务当前状态。
     */
    private function createBidSubmittedEvent(Task $task, Bid $bid, string $employeeNo, int $attachmentCount): void
    {
        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'BID_SUBMITTED',
            'operator_id' => $employeeNo,
            'from_status' => $task->status,
            'to_status' => $task->status,
            'related_type' => 'BID',
            'related_id' => $bid->id,
            'event_data' => [
                'amount' => $bid->amount,
                'deliveryDate' => $bid->delivery_date?->toDateString(),
                'revisionNo' => $bid->revision_no,
                'attachmentCount' => $attachmentCount,
            ],
            'remark' => '开发者提交投标',
        ]);
    }
}

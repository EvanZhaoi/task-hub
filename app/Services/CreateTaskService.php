<?php

namespace App\Services;

use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Payment\PaymentAccountException;
use App\Integrations\Sso\SsoUser;
use App\Models\AttachmentRef;
use App\Models\Task;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;

/**
 * 发布任务业务服务。
 *
 * Controller 只负责接收 HTTP 请求和返回响应。
 * 这个 Service 负责完整的“发布一个招标任务”业务动作：
 * 1. 清洗富文本描述。
 * 2. 根据付款账号 ID 查询外部账号快照。
 * 3. 在同一个数据库事务中创建 Task、TaskEvent 和 AttachmentRef。
 */
class CreateTaskService
{
    /**
     * 创建发布任务服务。
     *
     * Laravel 会通过服务容器自动注入这些依赖，不需要手工 new。
     */
    public function __construct(
        private readonly PaymentAccountClient $paymentAccounts,
        private readonly RichTextSanitizer $richTextSanitizer,
        private readonly SnowflakeId $ids,
    ) {}

    /**
     * 执行发布招标任务流程。
     *
     * @param  array<string, mixed>  $validated  StoreTaskRequest 已校验通过的表单数据。
     * @param  list<string>  $attachmentIds  已解析并去重后的外部附件 ID。
     * @throws PaymentAccountException 当付款账号接口不可用或账号不存在时抛出，由 Controller 转成表单错误。
     */
    public function execute(array $validated, array $attachmentIds, SsoUser $user, string $accessToken): void
    {
        // 富文本必须在入库前清洗；前端 Tiptap 只负责编辑体验，不是可信安全边界。
        $validated['description'] = $this->richTextSanitizer->clean($validated['description'] ?? null);

        // 付款账号是外部主数据，前端只提交 ID；名称和部门快照必须由后端从外部账号列表中匹配。
        // 外部查询放在事务外，避免数据库事务等待网络请求。
        $paymentAccount = $this->paymentAccounts->fetchById(
            (string) $validated['paymentAccountId'],
            $accessToken,
        );

        DB::transaction(function () use ($attachmentIds, $paymentAccount, $user, $validated): void {
            $task = $this->createTask($validated, $paymentAccount, $user);

            $this->createTaskCreatedEvent($task, $user);
            $this->createTaskPublishedEvent($task, $user, count($attachmentIds));
            $this->saveAttachments($task, $attachmentIds, $user);
        });
    }

    /**
     * 创建 task 主表记录。
     *
     * 当前发布入口只创建招标任务：
     * assignment_type 固定为 BIDDING，发布后直接进入 OPEN。
     *
     * @param  array<string, mixed>  $validated
     */
    private function createTask(array $validated, PaymentAccount $paymentAccount, SsoUser $user): Task
    {
        $employeeNo = $user->employeeNo();

        return Task::query()->create([
            'id' => $this->ids->next(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'payment_account_id' => $paymentAccount->accountId(),
            'payment_account_snapshot' => $this->paymentAccountSnapshot($paymentAccount),
            'budget' => $validated['budget'],
            'expected_delivery' => $validated['expectedDelivery'],
            'bidding_deadline' => $validated['biddingDeadline'],
            'status' => 'OPEN',
            'assignment_type' => 'BIDDING',
            'complexity' => $validated['complexity'],
            'created_by' => $employeeNo,
            'created_by_snapshot' => $this->createdBySnapshot($user),
            'version' => 0,
            'updated_by' => $employeeNo,
        ]);
    }

    /**
     * 写入任务创建事件。
     *
     * TASK_CREATED 记录任务第一次进入系统时的关键快照。
     */
    private function createTaskCreatedEvent(Task $task, SsoUser $user): void
    {
        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_CREATED',
            'operator_id' => $user->employeeNo(),
            'from_status' => null,
            'to_status' => 'OPEN',
            'event_data' => [
                'title' => $task->title,
                'budget' => $task->budget,
                'complexity' => $task->complexity,
                'assignmentType' => $task->assignment_type,
            ],
            'remark' => '发布者创建并发布任务',
        ]);
    }

    /**
     * 写入任务发布事件。
     *
     * 当前没有草稿流程，但单独记录 TASK_PUBLISHED 可以为以后草稿发布保留清晰事件语义。
     */
    private function createTaskPublishedEvent(Task $task, SsoUser $user, int $attachmentCount): void
    {
        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_PUBLISHED',
            'operator_id' => $user->employeeNo(),
            'from_status' => 'DRAFT',
            'to_status' => 'OPEN',
            'event_data' => [
                'biddingDeadline' => $task->bidding_deadline?->toISOString(),
                'attachmentCount' => $attachmentCount,
            ],
            'remark' => '任务进入招标中',
        ]);
    }

    /**
     * 保存任务附件引用。
     *
     * TaskHub 不保存真实文件，只保存外部上传接口返回的附件 ID。
     *
     * @param  list<string>  $attachmentIds
     */
    private function saveAttachments(Task $task, array $attachmentIds, SsoUser $user): void
    {
        if ($attachmentIds === []) {
            return;
        }

        $now = now();

        AttachmentRef::query()->insert(array_map(
            fn (string $attachmentId): array => [
                'id' => $this->ids->next(),
                'owner_type' => 'TASK',
                'owner_id' => $task->id,
                'attachment_id' => $attachmentId,
                'uploaded_by' => $user->employeeNo(),
                'created_at' => $now,
            ],
            $attachmentIds,
        ));
    }

    /**
     * 生成任务发布者历史快照。
     *
     * 快照只用于历史展示和审计，不能用于权限判断。
     * 权限判断仍应基于实时当前用户和后端角色规则。
     */
    private function createdBySnapshot(SsoUser $user): array
    {
        // 快照用于历史展示和审计；权限判断仍使用实时外部人员接口。
        return array_filter([
            'userId' => $user->employeeNo(),
            'employeeNo' => $user->employeeNo(),
            'displayName' => $user->displayName(),
            'departmentId' => $user->departmentId(),
            'departmentName' => $user->departmentName(),
            // avatarId 只保存头像 ID，不拼头像 URL；未来头像服务规则确认后再统一生成完整地址。
            'avatarId' => $user->avatarId(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * 生成付款账号历史快照。
     *
     * 任务保存的是发布当时选择的付款账号展示信息，
     * 后续外部账号名称变化不会影响已有任务的历史展示。
     */
    private function paymentAccountSnapshot(PaymentAccount $paymentAccount): array
    {
        // 保留该方法用于表达业务语义：Task 保存的是付款账号历史快照，不是实时主数据。
        return $paymentAccount->toSnapshot();
    }
}

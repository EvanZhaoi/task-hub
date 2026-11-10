<?php

namespace App\Services;

use App\Integrations\Payment\PaymentAccount;
use App\Integrations\Payment\PaymentAccountClient;
use App\Integrations\Payment\PaymentAccountException;
use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelException;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoUser;
use App\Models\AttachmentRef;
use App\Models\Task;
use App\Models\TaskAssignee;
use App\Models\TaskEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 直接指定成员发布任务的业务服务。
 *
 * DIRECT 模式不创建 Bid，任务创建后立即进入 ASSIGNED，并在同一个事务中写入
 * 正式执行成员、附件和关键事件。外部付款账号与人员查询放在事务外，避免持锁等待网络。
 */
class CreateDirectTaskService
{
    /**
     * 注入直接指派流程需要的外部主数据客户端和项目公共能力。
     */
    public function __construct(
        private readonly PaymentAccountClient $paymentAccounts,
        private readonly PersonnelClient $personnel,
        private readonly RichTextSanitizer $richTextSanitizer,
        private readonly SnowflakeId $ids,
    ) {}

    /**
     * 执行完整的直接指派发布流程。
     *
     * @param  array<string, mixed>  $validated  StoreTaskRequest 已校验的数据
     * @param  list<array{id: string, name: string}>  $attachments  已上传并去重的附件引用
     *
     * @throws PaymentAccountException 付款账号不可用时抛出
     * @throws PersonnelException 人员目录不可用时抛出
     * @throws ValidationException 负责人或协作成员不符合业务规则时抛出
     */
    public function execute(array $validated, array $attachments, SsoUser $user, string $accessToken): void
    {
        // 富文本入库前必须由后端白名单清洗，不能信任浏览器提交的 HTML。
        $validated['description'] = $this->richTextSanitizer->clean($validated['description'] ?? null);

        // 两个外部主数据查询都放在事务外，防止网络延迟扩大数据库锁持有时间。
        $paymentAccount = $this->paymentAccounts->fetchById(
            (string) $validated['paymentAccountId'],
            $accessToken,
        );
        $members = $this->resolveMembers($validated, $accessToken);

        DB::transaction(function () use ($attachments, $members, $paymentAccount, $user, $validated): void {
            $task = $this->createTask($validated, $paymentAccount, $user, $members['owner']->employeeNo());

            $this->saveAssignees($task, $members['owner'], $members['collaborators']);
            $this->saveAttachments($task, $attachments, $user);
            $this->createEvents($task, $user, count($members['collaborators']), count($attachments));
        });
    }

    /**
     * 从人员目录解析并校验负责人和协作成员。
     *
     * @param  array<string, mixed>  $validated
     * @return array{owner: PersonnelUser, collaborators: list<PersonnelUser>}
     */
    private function resolveMembers(array $validated, string $accessToken): array
    {
        $usersByEmployeeNo = [];

        foreach ($this->personnel->fetchAll($accessToken) as $person) {
            $employeeNo = $person->employeeNo();

            if ($employeeNo !== '') {
                $usersByEmployeeNo[$employeeNo] = $person;
            }
        }

        $ownerEmployeeNo = PersonnelUser::normalizeEmployeeNo((string) $validated['ownerEmployeeNo']);

        if ($ownerEmployeeNo === null || ! isset($usersByEmployeeNo[$ownerEmployeeNo])) {
            throw ValidationException::withMessages([
                'ownerEmployeeNo' => '选择的任务负责人不在当前人员列表中，请刷新后重新选择。',
            ]);
        }

        $collaboratorEmployeeNos = array_values(array_unique(array_filter(
            array_map(
                fn (mixed $employeeNo): ?string => PersonnelUser::normalizeEmployeeNo((string) $employeeNo),
                $validated['collaboratorEmployeeNos'] ?? [],
            ),
            fn (?string $employeeNo): bool => $employeeNo !== null,
        )));

        if (in_array($ownerEmployeeNo, $collaboratorEmployeeNos, true)) {
            throw ValidationException::withMessages([
                'collaboratorEmployeeNos' => '任务负责人不能同时作为协作成员。',
            ]);
        }

        $collaborators = [];

        foreach ($collaboratorEmployeeNos as $employeeNo) {
            if (! isset($usersByEmployeeNo[$employeeNo])) {
                throw ValidationException::withMessages([
                    'collaboratorEmployeeNos' => "协作成员 {$employeeNo} 不在当前人员列表中，请刷新后重新选择。",
                ]);
            }

            $collaborators[] = $usersByEmployeeNo[$employeeNo];
        }

        return [
            'owner' => $usersByEmployeeNo[$ownerEmployeeNo],
            'collaborators' => $collaborators,
        ];
    }

    /**
     * 创建直接指派任务主记录。
     *
     * @param  array<string, mixed>  $validated
     */
    private function createTask(
        array $validated,
        PaymentAccount $paymentAccount,
        SsoUser $user,
        string $ownerEmployeeNo,
    ): Task {
        $employeeNo = $user->employeeNo();

        return Task::query()->create([
            'id' => $this->ids->next(),
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'payment_account_id' => $paymentAccount->accountId(),
            'payment_account_snapshot' => $paymentAccount->toSnapshot(),
            'budget' => $validated['budget'],
            // DIRECT 没有后续报价，发布金额和交期就是当前确定值。
            'final_amount' => $validated['budget'],
            'expected_delivery' => $validated['expectedDelivery'],
            'final_delivery' => $validated['expectedDelivery'],
            'bidding_deadline' => null,
            'status' => 'ASSIGNED',
            'assignment_type' => 'DIRECT',
            'complexity' => $validated['complexity'],
            'is_urgent' => $validated['isUrgent'],
            'created_by' => $employeeNo,
            'created_by_snapshot' => $this->createdBySnapshot($user),
            'assigned_bid_id' => null,
            'primary_assignee_id' => $ownerEmployeeNo,
            'version' => 0,
            'updated_by' => $employeeNo,
        ]);
    }

    /**
     * 写入一个 OWNER 和零到多个 COLLABORATOR，形成正式任务成员关系。
     *
     * @param  list<PersonnelUser>  $collaborators
     */
    private function saveAssignees(Task $task, PersonnelUser $owner, array $collaborators): void
    {
        $createdAt = now();
        $members = [
            ['user_id' => $owner->employeeNo(), 'role' => 'OWNER'],
            ...array_map(
                fn (PersonnelUser $person): array => [
                    'user_id' => $person->employeeNo(),
                    'role' => 'COLLABORATOR',
                ],
                $collaborators,
            ),
        ];

        TaskAssignee::query()->insert(array_map(
            fn (array $member): array => [
                'id' => $this->ids->next(),
                'task_id' => $task->id,
                'user_id' => $member['user_id'],
                'role' => $member['role'],
                'created_at' => $createdAt,
            ],
            $members,
        ));
    }

    /**
     * 保存任务附件引用，真实文件仍由总部文件服务管理。
     *
     * @param  list<array{id: string, name: string}>  $attachments
     */
    private function saveAttachments(Task $task, array $attachments, SsoUser $user): void
    {
        if ($attachments === []) {
            return;
        }

        $createdAt = now();

        AttachmentRef::query()->insert(array_map(
            fn (array $attachment): array => [
                'id' => $this->ids->next(),
                'owner_type' => 'TASK',
                'owner_id' => $task->id,
                'attachment_id' => $attachment['id'],
                'attachment_name' => $attachment['name'],
                'uploaded_by' => $user->employeeNo(),
                'created_at' => $createdAt,
            ],
            $attachments,
        ));
    }

    /**
     * 写入任务创建和直接指派两个关键事件。
     */
    private function createEvents(
        Task $task,
        SsoUser $user,
        int $collaboratorCount,
        int $attachmentCount,
    ): void {
        $eventSnapshot = [
            'assignmentType' => 'DIRECT',
            'primaryAssigneeId' => $task->primary_assignee_id,
            'collaboratorCount' => $collaboratorCount,
            'attachmentCount' => $attachmentCount,
        ];

        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_CREATED',
            'operator_id' => $user->employeeNo(),
            'operator_name' => $user->displayName(),
            'from_status' => null,
            'to_status' => 'ASSIGNED',
            'event_data' => [
                ...$eventSnapshot,
                'title' => $task->title,
                'budget' => $task->budget,
                'complexity' => $task->complexity,
                'isUrgent' => $task->is_urgent,
            ],
            'remark' => '发布者创建直接指派任务',
        ]);

        TaskEvent::query()->create([
            'id' => $this->ids->next(),
            'task_id' => $task->id,
            'event_type' => 'TASK_ASSIGNED',
            'operator_id' => $user->employeeNo(),
            'operator_name' => $user->displayName(),
            'from_status' => 'DRAFT',
            'to_status' => 'ASSIGNED',
            'event_data' => $eventSnapshot,
            'remark' => '任务已直接指派执行成员',
        ]);
    }

    /**
     * 生成发布者历史展示快照；权限判断仍使用实时外部人员信息。
     */
    private function createdBySnapshot(SsoUser $user): array
    {
        return array_filter([
            'userId' => $user->employeeNo(),
            'employeeNo' => $user->employeeNo(),
            'displayName' => $user->displayName(),
            'departmentId' => $user->departmentId(),
            'departmentName' => $user->departmentName(),
            // 邮箱只作为后端邮件通知地址快照，不用于权限判断。
            'email' => $user->email(),
            'avatarId' => $user->avatarId(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }
}

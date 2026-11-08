<?php

namespace App\Services;

use App\Integrations\Personnel\PersonnelClient;
use App\Integrations\Personnel\PersonnelUser;
use App\Integrations\Sso\SsoUser;
use App\Mail\BidSelectedMail;
use App\Mail\BidSubmittedMail;
use App\Models\Bid;
use App\Models\Task;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * 发送任务核心业务邮件的公共服务。
 *
 * 本服务只负责收件人解析和邮件发送，不修改任务、投标或成员数据。
 * 调用方必须在业务事务成功提交后调用，邮件失败只记录日志，不能回滚核心业务。
 */
class TaskEmailNotificationService
{
    /**
     * 注入本据点人员目录，用工号解析姓名和邮箱。
     */
    public function __construct(
        private readonly PersonnelClient $personnel,
    ) {}

    /**
     * 有人投标后通知任务发布者。
     *
     * 新任务优先使用发布时保存的邮箱快照；历史任务没有邮箱时，再从人员目录按工号查找。
     */
    public function notifyBidSubmitted(
        Task $task,
        Bid $bid,
        SsoUser $bidder,
        string $accessToken,
    ): void {
        $snapshot = is_array($task->created_by_snapshot) ? $task->created_by_snapshot : [];
        $email = $this->validEmail($snapshot['email'] ?? null);
        $recipientName = $this->nullableString($snapshot['displayName'] ?? null) ?? (string) $task->created_by;

        if ($email === null) {
            $publisher = $this->findPersonnel((string) $task->created_by, $accessToken, (string) $task->id);
            $email = $this->validEmail($publisher?->eibEmail());
            $recipientName = $publisher?->displayName() ?? $recipientName;
        }

        if ($email === null) {
            Log::warning('投标邮件未发送：任务发布者没有可用邮箱。', [
                'task_id' => (string) $task->id,
                'recipient_employee_no' => (string) $task->created_by,
                'notification_type' => 'BID_SUBMITTED',
            ]);

            return;
        }

        $this->sendSafely(
            email: $email,
            recipientName: $recipientName,
            mail: new BidSubmittedMail(
                taskTitle: (string) $task->title,
                taskUrl: route('tasks.show', $task),
                bidderName: $bidder->displayName() ?? $bidder->employeeNo(),
                amount: $this->formatAmount($bid->amount),
                deliveryDate: $bid->delivery_date?->toDateString() ?? '-',
            ),
            taskId: (string) $task->id,
            employeeNo: (string) $task->created_by,
            notificationType: 'BID_SUBMITTED',
        );
    }

    /**
     * 选标成功后分别通知中标 OWNER 和全部 COLLABORATOR。
     *
     * 每位成员单独发送，避免把其他成员邮箱暴露在 To/CC 列表中。
     *
     * @param  list<array{employeeNo: string, role: string}>  $members
     */
    public function notifyBidSelected(
        Task $task,
        Bid $bid,
        array $members,
        string $accessToken,
    ): void {
        $directory = $this->personnelDirectory($accessToken, (string) $task->id);
        $sentEmails = [];

        foreach ($members as $member) {
            $employeeNo = PersonnelUser::normalizeEmployeeNo($member['employeeNo'] ?? null);

            if ($employeeNo === null) {
                continue;
            }

            $person = $directory[$employeeNo] ?? null;
            $email = $this->validEmail($person?->eibEmail());

            if ($email === null) {
                Log::warning('选标邮件未发送：中标成员没有可用邮箱。', [
                    'task_id' => (string) $task->id,
                    'recipient_employee_no' => $employeeNo,
                    'notification_type' => 'BID_SELECTED',
                ]);

                continue;
            }

            // 同一邮箱只发送一次，避免外部人员数据重复导致重复通知。
            $emailKey = strtolower($email);

            if (isset($sentEmails[$emailKey])) {
                continue;
            }

            $sentEmails[$emailKey] = true;
            $role = ($member['role'] ?? '') === 'OWNER' ? '负责人' : '协作成员';

            $this->sendSafely(
                email: $email,
                recipientName: $person?->displayName() ?? $employeeNo,
                mail: new BidSelectedMail(
                    recipientName: $person?->displayName() ?? $employeeNo,
                    memberRole: $role,
                    taskTitle: (string) $task->title,
                    taskUrl: route('tasks.show', $task),
                    amount: $this->formatAmount($bid->amount),
                    deliveryDate: $bid->delivery_date?->toDateString() ?? '-',
                ),
                taskId: (string) $task->id,
                employeeNo: $employeeNo,
                notificationType: 'BID_SELECTED',
            );
        }
    }

    /**
     * 按工号查找一名人员；接口异常时记录日志并返回 null。
     */
    private function findPersonnel(string $employeeNo, string $accessToken, string $taskId): ?PersonnelUser
    {
        try {
            return $this->personnel->findByEmployeeNo($employeeNo, $accessToken);
        } catch (Throwable $exception) {
            Log::warning('业务邮件收件人查询失败。', [
                'task_id' => $taskId,
                'recipient_employee_no' => $employeeNo,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * 一次读取人员目录并按标准化工号建立索引；接口异常时返回空目录。
     *
     * @return array<string, PersonnelUser>
     */
    private function personnelDirectory(string $accessToken, string $taskId): array
    {
        try {
            $people = $this->personnel->fetchAll($accessToken);
        } catch (Throwable $exception) {
            Log::warning('选标邮件收件人列表查询失败。', [
                'task_id' => $taskId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return [];
        }

        $directory = [];

        foreach ($people as $person) {
            $employeeNo = $person->employeeNo();

            if ($employeeNo !== '') {
                $directory[$employeeNo] = $person;
            }
        }

        return $directory;
    }

    /**
     * 安全发送单封邮件。
     *
     * SMTP 或模板异常只写日志；核心业务已经提交，不能因为通知失败对用户返回业务失败。
     */
    private function sendSafely(
        string $email,
        string $recipientName,
        Mailable $mail,
        string $taskId,
        string $employeeNo,
        string $notificationType,
    ): void {
        try {
            Mail::to($email, $recipientName)->send($mail);
        } catch (Throwable $exception) {
            Log::error('TaskHub 业务邮件发送失败。', [
                'task_id' => $taskId,
                'recipient_employee_no' => $employeeNo,
                'notification_type' => $notificationType,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * 校验并返回可用邮箱，非法或空邮箱统一返回 null。
     */
    private function validEmail(mixed $email): ?string
    {
        if (! is_string($email)) {
            return null;
        }

        $email = trim($email);

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    /**
     * 将外部快照字段转换为非空字符串。
     */
    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * 按人民币格式展示金额，邮件中不直接输出数据库 decimal 原始值。
     */
    private function formatAmount(mixed $amount): string
    {
        return '¥'.number_format((float) $amount, 2);
    }
}

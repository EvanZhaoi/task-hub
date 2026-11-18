<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 任务被发布者确认完成后发送给正式执行成员的邮件。
 */
class TaskCompletedMail extends Mailable
{
    /**
     * 创建完成通知所需的只读展示数据。
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $memberRole,
        public readonly string $taskTitle,
        public readonly string $taskUrl,
        public readonly string $completedAt,
    ) {}

    /**
     * 定义任务完成通知的中文主题。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[TaskHub] 任务已完成：{$this->taskTitle}",
        );
    }

    /**
     * 指定任务完成纯文本邮件模板。
     */
    public function content(): Content
    {
        return new Content(
            text: 'mail.tasks.task-completed',
        );
    }
}

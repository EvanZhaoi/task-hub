<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 招标截止且没有有效投标时发送给任务发布者的流标通知。
 */
class TaskFailedNoBidMail extends Mailable
{
    /**
     * 创建自动流标通知需要的只读展示数据。
     */
    public function __construct(
        public readonly string $taskTitle,
        public readonly string $taskUrl,
        public readonly string $biddingDeadline,
        public readonly string $reason,
    ) {}

    /**
     * 定义收件箱中显示的中文主题。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[TaskHub] 任务已流标：{$this->taskTitle}",
        );
    }

    /**
     * 指定自动流标 Markdown 邮件模板。
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tasks.task-failed-no-bid',
        );
    }
}

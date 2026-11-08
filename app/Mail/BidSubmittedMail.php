<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 有人提交投标后发送给任务发布者的邮件。
 *
 * 第一阶段不引入队列，因此该 Mailable 只负责邮件内容，不实现 ShouldQueue。
 */
class BidSubmittedMail extends Mailable
{
    /**
     * 创建投标通知邮件需要的只读展示数据。
     */
    public function __construct(
        public readonly string $taskTitle,
        public readonly string $taskUrl,
        public readonly string $bidderName,
        public readonly string $amount,
        public readonly string $deliveryDate,
    ) {}

    /**
     * 定义收件人邮箱中显示的中文主题。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[TaskHub] 收到新投标：{$this->taskTitle}",
        );
    }

    /**
     * 指定 Markdown 邮件模板。
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tasks.bid-submitted',
        );
    }
}

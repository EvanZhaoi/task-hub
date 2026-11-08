<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 投标被选中后发送给中标负责人和协作成员的邮件。
 */
class BidSelectedMail extends Mailable
{
    /**
     * 创建选标通知邮件需要的只读展示数据。
     */
    public function __construct(
        public readonly string $recipientName,
        public readonly string $memberRole,
        public readonly string $taskTitle,
        public readonly string $taskUrl,
        public readonly string $amount,
        public readonly string $deliveryDate,
    ) {}

    /**
     * 定义中标通知的中文主题。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[TaskHub] 投标已中标：{$this->taskTitle}",
        );
    }

    /**
     * 指定 Markdown 邮件模板。
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tasks.bid-selected',
        );
    }
}

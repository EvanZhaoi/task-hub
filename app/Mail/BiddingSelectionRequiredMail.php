<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * 招标截止且存在有效投标时发送给任务发布者的选标提醒。
 *
 * 第一阶段同步发送邮件，不引入队列；Mailable 只负责主题和模板数据。
 */
class BiddingSelectionRequiredMail extends Mailable
{
    /**
     * 创建待选标提醒邮件需要的只读展示数据。
     */
    public function __construct(
        public readonly string $taskTitle,
        public readonly string $taskUrl,
        public readonly string $biddingDeadline,
        public readonly int $activeBidCount,
    ) {}

    /**
     * 定义收件箱中显示的中文主题。
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[TaskHub] 招标已截止，请完成选标：{$this->taskTitle}",
        );
    }

    /**
     * 指定待选标 Markdown 邮件模板。
     */
    public function content(): Content
    {
        return new Content(
            markdown: 'mail.tasks.bidding-selection-required',
        );
    }
}

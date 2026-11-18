<x-mail::message>
# 任务已完成

{{ $recipientName }}，你参与的任务 **{{ $taskTitle }}** 已由发布者确认完成。

- 你的任务角色：{{ $memberRole }}
- 完成时间：{{ $completedAt }}

第一期暂未接入正式交付验收流程，本次完成记录由发布者人工确认，任务历史和业务时间线会继续保留。

<x-mail::button :url="$taskUrl">
查看任务详情
</x-mail::button>

此邮件由系统自动发送，请勿直接回复。<br>
{{ config('app.name') }}
</x-mail::message>

<x-mail::message>
# 任务已自动流标

任务 **{{ $taskTitle }}** 在招标截止时没有有效投标，系统已将任务标记为“已流标”。

- 招标截止时间：{{ $biddingDeadline }}
- 流标原因：{{ $reason }}

原任务和业务时间线会继续保留。如仍需开展该任务，请后续重新发布并设置新的招标截止时间。

<x-mail::button :url="$taskUrl">
查看任务详情
</x-mail::button>

此邮件由系统自动发送，请勿直接回复。<br>
{{ config('app.name') }}
</x-mail::message>

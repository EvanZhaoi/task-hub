<x-mail::message>
# 招标已截止，请完成选标

任务 **{{ $taskTitle }}** 已到招标截止时间，当前有 **{{ $activeBidCount }}** 份有效投标等待选择。

- 招标截止时间：{{ $biddingDeadline }}
- 当前状态：待选标

请进入任务详情查看投标方案，并选择合适的负责人和协作成员。

<x-mail::button :url="$taskUrl">
进入任务选标
</x-mail::button>

此邮件由系统自动发送，请勿直接回复。<br>
{{ config('app.name') }}
</x-mail::message>

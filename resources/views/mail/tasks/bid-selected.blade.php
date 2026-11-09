<x-mail::message>
# 投标已中标

{{ $recipientName }}，你参与的任务 **{{ $taskTitle }}** 已完成选标。

- 你的任务角色：{{ $memberRole }}
- 成交金额：{{ $amount }}
- 确定交付日期：{{ $deliveryDate }}

任务已经进入“进行中”，请进入任务详情查看正式执行成员和任务要求。

<x-mail::button :url="$taskUrl">
查看任务详情
</x-mail::button>

此邮件由系统自动发送，请勿直接回复。<br>
{{ config('app.name') }}
</x-mail::message>

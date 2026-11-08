<x-mail::message>
# 任务收到新投标

任务 **{{ $taskTitle }}** 收到一份新的投标方案。

- 投标人：{{ $bidderName }}
- 投标金额：{{ $amount }}
- 承诺交付日期：{{ $deliveryDate }}

请进入任务详情查看方案说明、协作成员和附件，并根据实际情况完成选标。

<x-mail::button :url="$taskUrl">
查看任务详情
</x-mail::button>

此邮件由系统自动发送，请勿直接回复。<br>
{{ config('app.name') }}
</x-mail::message>

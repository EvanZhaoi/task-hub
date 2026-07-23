<?php

return [
    // 外部付款账号服务基础地址，例如 https://payment.example.com。
    'base_url' => env('PAYMENT_ACCOUNT_BASE_URL'),
    // 查询全部付款账号的 path，用于发布任务弹窗中的 Select 选项。
    'list_path' => env('PAYMENT_ACCOUNT_LIST_PATH', env('PAYMENT_ACCOUNT_PATH')),
    // 查询单个付款账号详情的 path；如果为空，后端会从全部账号列表中按 accountId 查找。
    'detail_path' => env('PAYMENT_ACCOUNT_DETAIL_PATH'),
    // 当前支持 GET 和 POST；真实协议确认后按外部接口填写。
    'method' => env('PAYMENT_ACCOUNT_METHOD', 'GET'),
    // 外部接口超时时间，避免发布任务请求长时间挂起。
    'timeout' => (int) env('PAYMENT_ACCOUNT_TIMEOUT', 3),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('PAYMENT_ACCOUNT_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

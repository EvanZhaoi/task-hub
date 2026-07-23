<?php

return [
    // 外部付款账号服务基础地址，例如 https://payment.example.com。
    'base_url' => env('PAYMENT_ACCOUNT_BASE_URL'),
    // 查询付款账号详情的 path，只写 path，不写完整 URL。
    'path' => env('PAYMENT_ACCOUNT_PATH'),
    // 当前支持 GET 和 POST；真实协议确认后按总部接口填写。
    'method' => env('PAYMENT_ACCOUNT_METHOD', 'GET'),
    // 外部接口超时时间，避免发布任务请求长时间挂起。
    'timeout' => (int) env('PAYMENT_ACCOUNT_TIMEOUT', 3),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('PAYMENT_ACCOUNT_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

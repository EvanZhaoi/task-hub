<?php

return [
    // 本据点全员人员服务基础地址，例如 https://personnel.example.com。
    'base_url' => env('PERSONNEL_BASE_URL'),
    // 查询本据点全员列表的 path，只写 path，不写完整 URL。
    'list_path' => env('PERSONNEL_LIST_PATH'),
    // 当前支持 GET 和 POST；真实协议确认后按外部接口填写。
    'method' => env('PERSONNEL_METHOD', 'GET'),
    // 外部接口超时时间，避免登录流程长时间等待人员列表接口。
    'timeout' => (int) env('PERSONNEL_TIMEOUT', 3),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('PERSONNEL_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
    // 本据点人员列表数据量较大，运行时优先读 Redis 缓存。
    'cache_store' => env('EXTERNAL_DIRECTORY_CACHE_STORE', 'redis'),
    'cache_key' => env('PERSONNEL_CACHE_KEY', 'taskhub:personnel_users'),
    'cache_ttl' => (int) env('EXTERNAL_DIRECTORY_CACHE_TTL', 86400),
];

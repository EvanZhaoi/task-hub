<?php

return [
    // 总部文件服务基础地址，只放域名和公共前缀，例如 https://sso.example.com。
    'base_url' => env('FILE_OPERATION_BASE_URL'),
    // 总部文件上传接口 path，只写 path，不写完整 URL。
    'upload_path' => env('FILE_OPERATION_UPLOAD_PATH', '/file-operation/file/upload'),
    // 总部分配给 TaskHub 的文件服务标识，后端上传时作为 serviceKey 提交。
    'service_key' => env('FILE_OPERATION_SERVICE_KEY'),
    // 外部接口调用超时时间，避免上传请求长时间挂起。
    'timeout' => (int) env('FILE_OPERATION_TIMEOUT', 10),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('FILE_OPERATION_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

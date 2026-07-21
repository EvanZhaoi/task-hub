<?php

return [
    // 公司 SSO 服务基础地址，只放域名和公共前缀，例如 https://sso.example.com。
    'base_url' => env('SSO_BASE_URL'),
    // 浏览器登录跳转完整地址；它可能不是 base_url + path，因此独立配置。
    'login_url' => env('SSO_LOGIN_URL'),
    // 公司统一退出地址；为空时只退出 TaskHub 本地 Session。
    'logout_url' => env('SSO_LOGOUT_URL'),
    // 公司分配给 TaskHub 的客户端标识。
    'client_id' => env('SSO_CLIENT_ID'),
    // 后端调用人员信息接口时使用，绝不能暴露给前端。
    'client_secret' => env('SSO_CLIENT_SECRET'),
    // SSO 登录时请求的权限范围；没有明确协议时保持空值。
    'scope' => env('SSO_SCOPE'),
    // SSO 回调到 TaskHub 的站内 path。
    'callback_path' => env('SSO_CALLBACK_PATH', '/sso/callback'),
    // 推荐使用的当前登录人接口 path，只写 path，不写完整 URL。
    'userinfo_path' => env('SSO_USERINFO_PATH'),
    // 早期文档中的 token 校验 path，保留兼容，优先级低于 userinfo_path。
    'validate_path' => env('SSO_VALIDATE_PATH'),
    // 公司接口调用超时时间，避免登录请求长时间挂起。
    'timeout' => (int) env('SSO_TIMEOUT', 3),
    // 本地/测试环境可关闭证书校验；生产环境建议设置为 true。
    'verify_ssl' => filter_var(env('SSO_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

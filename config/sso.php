<?php

return [
    'base_url' => env('SSO_BASE_URL'),
    'login_url' => env('SSO_LOGIN_URL'),
    'client_id' => env('SSO_CLIENT_ID'),
    'client_secret' => env('SSO_CLIENT_SECRET'),
    'scope' => env('SSO_SCOPE'),
    'callback_path' => env('SSO_CALLBACK_PATH', '/sso/callback'),
    'userinfo_path' => env('SSO_USERINFO_PATH'),
    'validate_path' => env('SSO_VALIDATE_PATH'),
    'timeout' => (int) env('SSO_TIMEOUT', 3),
    'verify_ssl' => filter_var(env('SSO_VERIFY_SSL', false), FILTER_VALIDATE_BOOLEAN),
];

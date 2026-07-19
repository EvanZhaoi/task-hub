<?php

return [
    'base_url' => env('SSO_BASE_URL'),
    'login_url' => env('SSO_LOGIN_URL'),
    'client_id' => env('SSO_CLIENT_ID'),
    'scope' => env('SSO_SCOPE'),
    'callback_path' => env('SSO_CALLBACK_PATH', '/sso/callback'),
    'validate_path' => env('SSO_VALIDATE_PATH'),
    'timeout' => (int) env('SSO_TIMEOUT', 5),
];

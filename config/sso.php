<?php

return [
    'base_url' => env('SSO_BASE_URL'),
    'validate_path' => env('SSO_VALIDATE_PATH', '/api/sso/validate'),
    'timeout' => (int) env('SSO_TIMEOUT', 5),
];

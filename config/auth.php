<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | TaskHub Phase 1 does not use Laravel's local user provider. Company SSO
    | is accessed through App\Services\CurrentUserService.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'sso'),
        'passwords' => null,
    ],

    'guards' => [
        'sso' => [
            'driver' => 'session',
            'provider' => 'sso',
        ],
    ],

    'providers' => [
        'sso' => [
            'driver' => 'sso',
        ],
    ],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

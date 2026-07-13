<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | TaskHub Phase 1 does not use Laravel's local users table or default
    | Eloquent user provider. Company SSO will be integrated through
    | App\Services\CurrentUserService and SSO middleware/session flow.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', null),
        'passwords' => null,
    ],

    'guards' => [],

    'providers' => [],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

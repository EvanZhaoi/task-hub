<?php

return [
    'default_role' => env('TASKHUB_DEFAULT_ROLE', 'DEVELOPER'),

    'roles' => [
        'ADMIN' => array_filter(array_map('trim', explode(',', (string) env('TASKHUB_ADMIN_EMPLOYEE_NOS', '')))),
        'BOSS' => array_filter(array_map('trim', explode(',', (string) env('TASKHUB_BOSS_EMPLOYEE_NOS', '')))),
        'PUBLISHER' => array_filter(array_map('trim', explode(',', (string) env('TASKHUB_PUBLISHER_EMPLOYEE_NOS', '')))),
        'DEVELOPER' => array_filter(array_map('trim', explode(',', (string) env('TASKHUB_DEVELOPER_EMPLOYEE_NOS', '')))),
    ],
];

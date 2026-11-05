<?php

declare(strict_types=1);

return [
    'name' => 'Admin',

    // Deliberately not the conventional "/admin" — reduces automated scanner
    // noise; not a real security boundary on its own (auth + roles are).
    'path' => env('ADMIN_PATH', 'control-panel'),

    // Used only by SuperAdminSeeder. Blank means the seeder creates nobody.
    'super_admin' => [
        'name' => env('SUPER_ADMIN_NAME', 'Happy Journey Admin'),
        'email' => env('SUPER_ADMIN_EMAIL', ''),
        'password' => env('SUPER_ADMIN_PASSWORD', ''),
    ],

    'ip_allowlist' => [
        'enabled' => env('ADMIN_IP_ALLOWLIST_ENABLED', false),
        'ips' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_IP_ALLOWLIST', '')))),
    ],

    'session' => [
        'idle_minutes' => (int) env('ADMIN_SESSION_IDLE_MINUTES', 20),
        'absolute_hours' => (int) env('ADMIN_SESSION_ABSOLUTE_HOURS', 8),
    ],

    'login' => [
        'max_attempts' => 5,
        'lockout_minutes' => 15,
    ],
];

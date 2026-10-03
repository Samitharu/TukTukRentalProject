<?php

declare(strict_types=1);

return [
    'name' => 'Admin',

    // Deliberately not the conventional "/admin" — reduces automated scanner
    // noise; not a real security boundary on its own (auth + 2FA + roles are).
    'path' => env('ADMIN_PATH', 'control-panel'),

    'ip_allowlist' => [
        'enabled' => env('ADMIN_IP_ALLOWLIST_ENABLED', false),
        'ips' => array_filter(array_map('trim', explode(',', (string) env('ADMIN_IP_ALLOWLIST', '')))),
    ],

    'session' => [
        'idle_minutes' => (int) env('ADMIN_SESSION_IDLE_MINUTES', 20),
        'absolute_hours' => (int) env('ADMIN_SESSION_ABSOLUTE_HOURS', 8),
    ],

    // Roles that must have two-factor authentication confirmed before they
    // can use any admin route beyond the 2FA setup screen itself.
    'two_factor_enforced_roles' => array_filter(array_map('trim', explode(',', (string) env('TWO_FACTOR_ENFORCED_ROLES', 'Super Admin')))),

    'login' => [
        'max_attempts' => 5,
        'lockout_minutes' => 15,
    ],
];

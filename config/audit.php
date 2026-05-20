<?php

declare(strict_types=1);

return [
    /*
     * Attributes excluded from all configured activity logs.
     */
    'default_except' => [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'google_token',
        'google_refresh_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ],

    /*
     * Per-model audit options consumed by Accelerator activity traits.
     */
    'models' => [],
];

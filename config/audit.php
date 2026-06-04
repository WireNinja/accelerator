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
     *
     * Attributes may be a list of attribute names or an associative map of
     * attribute names to UI labels, for example: ['name' => 'Nama'].
     */
    'models' => [],
];

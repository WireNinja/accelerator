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
     * Example:
     *
     * App\Models\User::class => [
     *     'log_name' => 'user',
     *     'attributes' => [
     *         'name',
     *         'email',
     *         'current_location_id',
     *         'currentLocation.name',
     *     ],
     *     'relationships' => [
     *         'roles' => 'roles.name',
     *     ],
     * ],
     */
    'models' => [],
];

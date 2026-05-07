<?php

use App\Enums\System\PanelEnum;
use App\Enums\System\ResourceEnum;
use App\Enums\System\RoleEnum;

return [
    'runtime' => env('SERVER_RUNTIME', 'fpm'), // 'swoole' or 'frankenphp' or 'fpm'

    'infra' => [
        'hosting' => env('INFRA_HOSTING', 'dedicated'), // 'shared' or 'dedicated'
    ],

    'enums' => [
        'role' => RoleEnum::class,
        'resource' => ResourceEnum::class,
        'panel' => PanelEnum::class,
        'launcher' => \App\Enums\System\LauncherEnum::class,
    ],

    'cache' => [
        'allow_swoole' => env('ACCELERATOR_CACHE_ALLOW_SWOOLE', true),
        'allow_redis' => env('ACCELERATOR_CACHE_ALLOW_REDIS', true),
        'allow_database' => env('ACCELERATOR_CACHE_ALLOW_DATABASE', true),
    ],

    'horizon' => [
        'auto_register' => true,
        'email_to' => env('HORIZON_EMAIL_TO'),
    ],
];

<?php

use App\Enums\System\LauncherEnum;
use App\Enums\System\PanelEnum;
use App\Enums\System\ResourceEnum;
use App\Enums\System\RoleEnum;

return [
    'runtime' => env('SERVER_RUNTIME', 'fpm'), // 'swoole' or 'frankenphp' or 'fpm'

    'infra' => [
        'hosting' => env('INFRA_HOSTING', 'dedicated'), // 'shared' or 'dedicated'
    ],

    'proxy' => [
        'trust_local' => env('ACCELERATOR_TRUST_LOCAL_PROXY', true),
    ],

    'middleware' => [
        'link_preload' => [
            // Master switch: kalau false, AddLinkHeadersForPreloadedAssets tidak akan
            // di-append sama sekali (hemat satu middleware untuk seluruh aplikasi).
            'enabled' => env('ACCELERATOR_LINK_PRELOAD_ENABLED', true),

            // Path prefix yang di-skip walaupun master switch on. Default: /admin/*
            // Filament admin biasanya pakai Livewire wire-navigate dan tidak butuh
            // preload header — append-nya cuma menambah noise.
            'skip_path_prefixes' => ['admin', 'admin/*'],
        ],
    ],

    'enums' => [
        'role' => RoleEnum::class,
        'resource' => ResourceEnum::class,
        'panel' => PanelEnum::class,
        'launcher' => LauncherEnum::class,
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

    'dev' => [
        'login_default' => env('DEV_LOGIN', null),
        'password_default' => env('DEV_PASSWORD', null),
    ],
];

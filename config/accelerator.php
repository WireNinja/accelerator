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

    'telemetry' => [
        'enabled' => env('ACCELERATOR_TELEMETRY_ENABLED', true),
        'flush_interval' => env('ACCELERATOR_TELEMETRY_FLUSH_INTERVAL', 5),
        'buffer_rows' => env('ACCELERATOR_TELEMETRY_BUFFER_ROWS', 128),
        'buffer_bytes' => env('ACCELERATOR_TELEMETRY_BUFFER_BYTES', 65535),
        'retention_days' => env('ACCELERATOR_TELEMETRY_RETENTION', 90),
        'pruning_enabled' => env('ACCELERATOR_TELEMETRY_PRUNING', true),
        'capture_guests' => env('ACCELERATOR_TELEMETRY_CAPTURE_GUESTS', false),
        'sample_rate' => env('ACCELERATOR_TELEMETRY_SAMPLE_RATE', 100),
        'notify' => [
            'discord_webhook' => env('ACCELERATOR_TELEMETRY_DISCORD_WEBHOOK'),
            'telegram_chat_id' => env('ACCELERATOR_TELEMETRY_TELEGRAM_CHAT'),
        ],
        'throttle_minutes' => env('ACCELERATOR_TELEMETRY_THROTTLE', 60),
        'capture_headers' => true,
        'capture_payload' => false,
        'sensitive_params' => [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'credit_card',
            'cvv',
            'ssn',
        ],
        'sensitive_headers' => [
            'Authorization',
            'Cookie',
            'X-CSRF-TOKEN',
        ],
    ],
];

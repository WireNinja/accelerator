<?php

use App\Enums\System\LauncherEnum;
use App\Enums\System\PanelEnum;
use App\Enums\System\ResourceEnum;
use App\Enums\System\RoleEnum;

return [
    'features' => [
        'filament' => env('ACCELERATOR_FEATURE_FILAMENT', false),
        'panels' => env('ACCELERATOR_FEATURE_PANELS', false),
        'oauth' => env('ACCELERATOR_FEATURE_OAUTH', false),
        'insider' => env('ACCELERATOR_FEATURE_INSIDER', false),
        'pwa' => env('ACCELERATOR_FEATURE_PWA', false),
        'settings' => env('ACCELERATOR_FEATURE_SETTINGS', false),
        'telegram' => env('ACCELERATOR_FEATURE_TELEGRAM', false),
        'telemetry' => env('ACCELERATOR_FEATURE_TELEMETRY', false),
        'ticketing' => env('ACCELERATOR_FEATURE_TICKETING', false),
    ],

    'infra' => [
        'hosting' => env('INFRA_HOSTING', 'dedicated'), // 'shared' or 'dedicated'
    ],

    'proxy' => [
        'trust_local' => env('ACCELERATOR_TRUST_LOCAL_PROXY', true),
    ],

    'assets' => [
        'iconify_url' => 'https://cdn.jsdelivr.net/npm/iconify-icon@3.0.2/dist/iconify-icon.min.js',
        'leaflet_js_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        'leaflet_css_url' => 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
    ],

    'enums' => [
        'role' => RoleEnum::class,
        'resource' => ResourceEnum::class,
        'panel' => PanelEnum::class,
        'launcher' => LauncherEnum::class,
    ],

    'horizon' => [
        'auto_register' => true,
        'email_to' => env('HORIZON_EMAIL_TO'),
    ],

    'dev' => [
        'login_default' => env('DEV_LOGIN', null),
        'password_default' => env('DEV_PASSWORD', null),
    ],

    'oauth' => [
        'mode' => env('ACCELERATOR_OAUTH_MODE', 'disabled'),
        'allowed_domains' => array_values(array_filter(explode(',', (string) env('ACCELERATOR_OAUTH_ALLOWED_DOMAINS', '')))),
    ],

    'uploads' => [
        'max_megabytes' => env('ACCELERATOR_UPLOAD_MAX_MB', 100),
    ],

    'dicebear' => [
        'url' => env('ACCELERATOR_DICEBEAR_URL', 'https://dicebear.ohmyserver.com'),
        'version' => env('ACCELERATOR_DICEBEAR_VERSION', '9.x'),
        'style' => env('ACCELERATOR_DICEBEAR_STYLE', 'notionists'),
    ],

    'telemetry' => [
        'enabled' => env('ACCELERATOR_FEATURE_TELEMETRY', false),
        'flush_interval' => env('ACCELERATOR_TELEMETRY_FLUSH_INTERVAL', 5),
        'buffer_rows' => env('ACCELERATOR_TELEMETRY_BUFFER_ROWS', 128),
        'buffer_bytes' => env('ACCELERATOR_TELEMETRY_BUFFER_BYTES', 65535),
        'retention_days' => env('ACCELERATOR_TELEMETRY_RETENTION', 90),
        'pruning_enabled' => env('ACCELERATOR_TELEMETRY_PRUNING', true),
        'capture_guests' => env('ACCELERATOR_TELEMETRY_CAPTURE_GUESTS', false),
        'sample_rate' => env('ACCELERATOR_TELEMETRY_SAMPLE_RATE', 100),
        'timeline_max_events' => 50,
        'timeline_sql_max_length' => 1000,
        'source_radius' => 5,
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

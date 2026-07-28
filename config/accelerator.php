<?php

use App\Enums\System\LauncherEnum;
use App\Enums\System\PanelEnum;
use App\Enums\System\RoleEnum;

return [
    'features' => [
        'filament' => env('ACCELERATOR_FEATURE_FILAMENT', false),
        'fortify' => env('ACCELERATOR_FEATURE_FORTIFY', false),
        'oauth' => env('ACCELERATOR_FEATURE_OAUTH', false),
        'insider' => env('ACCELERATOR_FEATURE_INSIDER', false),
        'pwa' => env('ACCELERATOR_FEATURE_PWA', false),
        'settings' => env('ACCELERATOR_FEATURE_SETTINGS', false),
        'telegram' => env('ACCELERATOR_FEATURE_TELEGRAM', false),
        'telemetry' => env('ACCELERATOR_FEATURE_TELEMETRY', false),
        'ticketing' => env('ACCELERATOR_FEATURE_TICKETING', false),
        'horizon' => env('ACCELERATOR_FEATURE_HORIZON', false),
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
        'panel' => PanelEnum::class,
        'launcher' => enum_exists(LauncherEnum::class) ? LauncherEnum::class : null,
    ],

    'horizon' => [
        'email_to' => env('HORIZON_EMAIL_TO'),
    ],

    'dev' => [
        'login_default' => env('DEV_LOGIN', null),
        'password_default' => env('DEV_PASSWORD', null),
    ],

    'oauth' => [
        'mode' => env('ACCELERATOR_OAUTH_MODE', 'disabled'),
        'allowed_domains' => array_values(array_filter(explode(',', (string) env('ACCELERATOR_OAUTH_ALLOWED_DOMAINS', '')))),
        'default_role' => env('ACCELERATOR_OAUTH_DEFAULT_ROLE', 'user'),
    ],

    'uploads' => [
        'max_megabytes' => env('ACCELERATOR_UPLOAD_MAX_MB', 100),
    ],

    'ui' => [
        'sidebar' => [
            'default_width' => 336,
            'min_width' => 288,
            'max_width' => 480,
            'rail_width' => 56,
        ],
    ],

    'support' => [
        'whatsapp' => env('ACCELERATOR_SUPPORT_WHATSAPP'),
        'telegram' => env('ACCELERATOR_SUPPORT_TELEGRAM'),
    ],

    'dicebear' => [
        'url' => env('ACCELERATOR_DICEBEAR_URL', 'https://dicebear.ohmyserver.com'),
        'version' => env('ACCELERATOR_DICEBEAR_VERSION', '9.x'),
        'style' => env('ACCELERATOR_DICEBEAR_STYLE', 'notionists'),
    ],

    'telemetry' => [
        'flush_interval' => env('ACCELERATOR_TELEMETRY_FLUSH_INTERVAL', 5),
        'buffer_rows' => env('ACCELERATOR_TELEMETRY_BUFFER_ROWS', 128),
        'buffer_bytes' => env('ACCELERATOR_TELEMETRY_BUFFER_BYTES', 65535),
        'retention_days' => env('ACCELERATOR_TELEMETRY_RETENTION', 90),
        'pruning_enabled' => env('ACCELERATOR_TELEMETRY_PRUNING', true),
        'sample_rate' => env('ACCELERATOR_TELEMETRY_SAMPLE_RATE', 100),
        'capture_headers' => env('ACCELERATOR_TELEMETRY_CAPTURE_HEADERS', false),
        'capture_query' => env('ACCELERATOR_TELEMETRY_CAPTURE_QUERY', false),
        'capture_payload' => env('ACCELERATOR_TELEMETRY_CAPTURE_PAYLOAD', false),
        'notification_retry_seconds' => env('ACCELERATOR_TELEMETRY_NOTIFICATION_RETRY', 60),
        'notification_attempts' => env('ACCELERATOR_TELEMETRY_NOTIFICATION_ATTEMPTS', 8),
        'notifications_per_flush' => env('ACCELERATOR_TELEMETRY_NOTIFICATIONS_PER_FLUSH', 10),
        'notify' => [
            'discord_webhook' => env('ACCELERATOR_TELEMETRY_DISCORD_WEBHOOK'),
            'telegram_chat_id' => env('ACCELERATOR_TELEMETRY_TELEGRAM_CHAT'),
            'telegram_bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'telegram_base_uri' => env('TELEGRAM_API_BASE_URI', 'https://api.telegram.org'),
        ],
        'sensitive_params' => [
            'password',
            'password_confirmation',
            'token',
            'secret',
            'authorization',
            'cookie',
            'session',
            'api_key',
            'credit_card',
            'cvv',
            'ssn',
        ],
        'sensitive_headers' => [
            'Authorization',
            'Cookie',
            'Proxy-Authorization',
            'X-CSRF-TOKEN',
            'X-XSRF-TOKEN',
        ],
    ],
];

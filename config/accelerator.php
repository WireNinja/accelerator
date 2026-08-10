<?php

return [
    'features' => [
        'oauth' => env('ACCELERATOR_FEATURE_OAUTH', false),
        'pwa' => env('ACCELERATOR_FEATURE_PWA', false),
        'telegram' => env('ACCELERATOR_FEATURE_TELEGRAM', false),
        'horizon' => env('ACCELERATOR_FEATURE_HORIZON', false),
        'reverb' => env('ACCELERATOR_FEATURE_REVERB', false),
        'scout' => env('ACCELERATOR_FEATURE_SCOUT', false),
        'nightowl' => env('ACCELERATOR_FEATURE_NIGHTOWL', false),
    ],

    'nightowl' => [
        'authenticated_request_sample_rate' => (float) env('NIGHTOWL_AUTHENTICATED_REQUEST_SAMPLE_RATE', 1.0),
    ],

    'pwa' => [
        'theme_color' => env('ACCELERATOR_PWA_THEME_COLOR', '#ffffff'),
    ],

    'proxy' => [
        'trust_local' => env('ACCELERATOR_TRUST_LOCAL_PROXY', true),
    ],

    'enums' => [
        'role' => 'App\\Enums\\System\\RoleEnum',
        'panel' => 'App\\Enums\\System\\PanelEnum',
        'launcher' => 'App\\Enums\\System\\LauncherEnum',
        'navigation_group' => 'App\\Enums\\System\\NavigationGroup',
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

    'backup' => [
        'enabled' => env('ACCELERATOR_BACKUP_ENABLED', true),
        'name' => env('ACCELERATOR_BACKUP_NAME', env('APP_NAME', 'accelerator')),
        'include' => [
            storage_path('app'),
        ],
        'disks' => array_values(array_filter(array_map(
            trim(...),
            explode(',', (string) env('ACCELERATOR_BACKUP_DISKS', 'local')),
        ))),
        'time' => env('ACCELERATOR_BACKUP_TIME', '02:00'),
        'maximum_age_days' => (int) env('ACCELERATOR_BACKUP_MAXIMUM_AGE_DAYS', 2),
        'maximum_storage_megabytes' => (int) env('ACCELERATOR_BACKUP_MAXIMUM_STORAGE_MEGABYTES', 5000),
        'retention' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
        ],
    ],

    'operations' => [
        'deployment_key' => env('ACCELERATOR_DEPLOYMENT_KEY', 'local'),
        'stage' => env('ACCELERATOR_DEPLOYMENT_STAGE', env('APP_ENV', 'local')),
        'domain' => env('APP_URL', ''),
        'deploy_root' => env('ACCELERATOR_DEPLOY_ROOT', ''),
        'telegram' => [
            'bot_token' => env('ACCELERATOR_TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('ACCELERATOR_TELEGRAM_CHAT_ID'),
            'notify_successes' => env('ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES', false),
            'base_uri' => env('TELEGRAM_API_BASE_URI', 'https://api.telegram.org'),
        ],
    ],

    'audit' => [
        'default_except' => [
            'password',
            'remember_token',
            'app_authentication_secret',
            'app_authentication_recovery_codes',
        ],
        'models' => [],
    ],

    'ui' => [
        'density' => env('ACCELERATOR_UI_DENSITY', 'compact'),
        'environment_indicator' => [
            'enabled' => env('ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED', false),
            'label' => env('ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL', ''),
            'color' => env('ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR', 'warning'),
        ],
        'sidebar' => [
            'default_width' => 336,
            'min_width' => 288,
            'max_width' => 480,
            'rail_width' => 56,
            'compact_rail_width' => 52,
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

];

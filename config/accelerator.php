<?php

use App\Enums\System\LauncherEnum;
use App\Enums\System\NavigationGroup;
use App\Enums\System\PanelEnum;
use App\Enums\System\RoleEnum;

return [
    'features' => [
        'oauth' => env('ACCELERATOR_FEATURE_OAUTH', false),
        'pwa' => env('ACCELERATOR_FEATURE_PWA', false),
        'telegram' => env('ACCELERATOR_FEATURE_TELEGRAM', false),
        'horizon' => env('ACCELERATOR_FEATURE_HORIZON', false),
        'reverb' => env('ACCELERATOR_FEATURE_REVERB', false),
        'scout' => env('ACCELERATOR_FEATURE_SCOUT', false),
        'nightwatch' => env('ACCELERATOR_FEATURE_NIGHTWATCH', false),
    ],

    'proxy' => [
        'trust_local' => env('ACCELERATOR_TRUST_LOCAL_PROXY', true),
    ],

    'enums' => [
        'role' => RoleEnum::class,
        'panel' => PanelEnum::class,
        'launcher' => enum_exists(LauncherEnum::class) ? LauncherEnum::class : null,
        'navigation_group' => enum_exists(NavigationGroup::class) ? NavigationGroup::class : null,
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
        'density' => env('ACCELERATOR_UI_DENSITY', 'compact'),
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

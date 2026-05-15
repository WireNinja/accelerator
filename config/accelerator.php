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

    'deploy' => [
        'default_stage' => env('OPS_DEPLOY_DEFAULT_STAGE', 'test'),
        'project' => env('OPS_DEPLOY_PROJECT', env('APP_NAME', 'laravel')),
        'server' => env('OPS_DEPLOY_SERVER', 'vps'),
        'keep_releases' => (int) env('OPS_DEPLOY_KEEP_RELEASES', 5),

        'shared' => [
            'files' => ['.env'],
            'directories' => ['storage'],
            'laravel_links' => [
                'public/storage' => 'storage/app/public',
            ],
        ],

        'stages' => [
            'test' => [
                'enabled' => env('OPS_DEPLOY_TEST_ENABLED', true),
                'domain' => env('OPS_DEPLOY_TEST_DOMAIN'),
                'root' => env('OPS_DEPLOY_TEST_ROOT'),
                'repo' => env('OPS_DEPLOY_TEST_REPO', env('OPS_DEPLOY_REPO')),
                'branch' => env('OPS_DEPLOY_TEST_BRANCH', env('OPS_DEPLOY_BRANCH', 'main')),
                'group' => env('OPS_DEPLOY_TEST_GROUP'),
                'runtime' => env('OPS_DEPLOY_TEST_RUNTIME', env('SERVER_RUNTIME', 'swoole')),
                'php_bin' => env('OPS_DEPLOY_TEST_PHP_BIN', env('OPS_DEPLOY_PHP_BIN', PHP_BINARY)),
                'bun_bin' => env('OPS_DEPLOY_TEST_BUN_BIN', env('OPS_DEPLOY_BUN_BIN', 'bun')),
                'run_user' => env('OPS_DEPLOY_TEST_RUN_USER', env('OPS_DEPLOY_RUN_USER', 'www-data')),
                'ports' => [
                    'octane' => (int) env('OPS_DEPLOY_TEST_OCTANE_PORT', 9012),
                    'reverb' => (int) env('OPS_DEPLOY_TEST_REVERB_PORT', 9013),
                    'nightwatch' => (int) env('OPS_DEPLOY_TEST_NIGHTWATCH_PORT', 2412),
                ],
                'ssl' => [
                    'enabled' => env('OPS_DEPLOY_TEST_SSL_ENABLED', true),
                    'provider' => env('OPS_DEPLOY_TEST_SSL_PROVIDER', 'certbot'),
                    'mode' => env('OPS_DEPLOY_TEST_SSL_MODE', 'webroot'),
                    'email' => env('OPS_DEPLOY_TEST_SSL_EMAIL', env('OPS_DEPLOY_SSL_EMAIL')),
                    'http_bootstrap' => env('OPS_DEPLOY_TEST_SSL_HTTP_BOOTSTRAP', true),
                ],
                'services' => [
                    'octane' => ['enabled' => env('OPS_DEPLOY_TEST_OCTANE_ENABLED', true)],
                    'horizon' => ['enabled' => env('OPS_DEPLOY_TEST_HORIZON_ENABLED', true)],
                    'reverb' => ['enabled' => env('OPS_DEPLOY_TEST_REVERB_ENABLED', true)],
                    'scheduler' => ['enabled' => env('OPS_DEPLOY_TEST_SCHEDULER_ENABLED', true)],
                    'nightwatch' => [
                        'enabled' => env('OPS_DEPLOY_TEST_NIGHTWATCH_ENABLED', false),
                        'host' => env('OPS_DEPLOY_TEST_NIGHTWATCH_HOST', '127.0.0.1'),
                        'port' => (int) env('OPS_DEPLOY_TEST_NIGHTWATCH_PORT', 2412),
                    ],
                ],
            ],

            'prod' => [
                'enabled' => env('OPS_DEPLOY_PROD_ENABLED', true),
                'domain' => env('OPS_DEPLOY_PROD_DOMAIN'),
                'root' => env('OPS_DEPLOY_PROD_ROOT'),
                'repo' => env('OPS_DEPLOY_PROD_REPO', env('OPS_DEPLOY_REPO')),
                'branch' => env('OPS_DEPLOY_PROD_BRANCH', env('OPS_DEPLOY_BRANCH', 'main')),
                'group' => env('OPS_DEPLOY_PROD_GROUP'),
                'runtime' => env('OPS_DEPLOY_PROD_RUNTIME', env('SERVER_RUNTIME', 'swoole')),
                'php_bin' => env('OPS_DEPLOY_PROD_PHP_BIN', env('OPS_DEPLOY_PHP_BIN', PHP_BINARY)),
                'bun_bin' => env('OPS_DEPLOY_PROD_BUN_BIN', env('OPS_DEPLOY_BUN_BIN', 'bun')),
                'run_user' => env('OPS_DEPLOY_PROD_RUN_USER', env('OPS_DEPLOY_RUN_USER', 'www-data')),
                'ports' => [
                    'octane' => (int) env('OPS_DEPLOY_PROD_OCTANE_PORT', 9014),
                    'reverb' => (int) env('OPS_DEPLOY_PROD_REVERB_PORT', 9015),
                    'nightwatch' => (int) env('OPS_DEPLOY_PROD_NIGHTWATCH_PORT', 2414),
                ],
                'ssl' => [
                    'enabled' => env('OPS_DEPLOY_PROD_SSL_ENABLED', true),
                    'provider' => env('OPS_DEPLOY_PROD_SSL_PROVIDER', 'certbot'),
                    'mode' => env('OPS_DEPLOY_PROD_SSL_MODE', 'webroot'),
                    'email' => env('OPS_DEPLOY_PROD_SSL_EMAIL', env('OPS_DEPLOY_SSL_EMAIL')),
                    'http_bootstrap' => env('OPS_DEPLOY_PROD_SSL_HTTP_BOOTSTRAP', true),
                ],
                'services' => [
                    'octane' => ['enabled' => env('OPS_DEPLOY_PROD_OCTANE_ENABLED', true)],
                    'horizon' => ['enabled' => env('OPS_DEPLOY_PROD_HORIZON_ENABLED', true)],
                    'reverb' => ['enabled' => env('OPS_DEPLOY_PROD_REVERB_ENABLED', true)],
                    'scheduler' => ['enabled' => env('OPS_DEPLOY_PROD_SCHEDULER_ENABLED', true)],
                    'nightwatch' => [
                        'enabled' => env('OPS_DEPLOY_PROD_NIGHTWATCH_ENABLED', false),
                        'host' => env('OPS_DEPLOY_PROD_NIGHTWATCH_HOST', '127.0.0.1'),
                        'port' => (int) env('OPS_DEPLOY_PROD_NIGHTWATCH_PORT', 2414),
                    ],
                ],
            ],
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

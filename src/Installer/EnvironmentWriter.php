<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Deployment\DeploymentConfig;

final readonly class EnvironmentWriter
{
    public function __construct(private InstallContext $context) {}

    /** @throws JsonException */
    public function write(): void
    {
        $this->writeEnvironmentFiles();
        $this->writeDeploymentFiles();
        $this->updateGitignore();
    }

    private function writeEnvironmentFiles(): void
    {
        $template = file_get_contents($this->context->packageRoot.'/.base-env.example');

        if (! is_string($template)) {
            throw new RuntimeException('Unable to read Accelerator environment template.');
        }

        $currentEnvironment = file_get_contents($this->context->projectRoot.'/.env');
        $appKey = is_string($currentEnvironment) ? $this->environmentValue($currentEnvironment, 'APP_KEY') : '';
        $appKey = $appKey !== '' ? $appKey : 'base64:'.base64_encode(random_bytes(32));
        $this->context->writeFile('.env', $this->renderEnvironment($template, $appKey), 0600);
        $this->context->writeFile('.env.example', $this->renderEnvironment($template, ''));
    }

    /** @throws JsonException */
    private function writeDeploymentFiles(): void
    {
        if (! $this->context->plan->deploy) {
            return;
        }

        $plan = $this->context->plan;
        $stage = fn (bool $enabled, string $domain): array => [
            'enabled' => $enabled,
            'ssh_host' => $plan->sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'http_runtime' => $plan->httpRuntime,
            'horizon' => $this->context->hasFeature('horizon'),
            'queue_worker' => ! $this->context->hasFeature('horizon'),
            'reverb' => $this->context->hasFeature('reverb'),
            'nightowl' => $this->context->hasFeature('nightowl'),
            'scheduler' => true,
            'health_path' => '/up',
        ];
        $document = [
            'schema' => 2,
            'default_stage' => $plan->deploymentMode === 'dual' ? 'staging' : 'production',
            'deployment_key' => $plan->deploymentKey,
            'repository' => $plan->repository,
            'branch' => $plan->repositoryBranch,
            'port_base' => $plan->portBase,
            'keep_releases' => 5,
            'php_version' => '8.5',
            'php_binary' => 'php8.5',
            'package_manager' => $plan->packageManager,
            'run_user' => 'www-data',
            'ssl_email' => $plan->adminEmail,
            'stages' => [
                'staging' => $stage($plan->deploymentMode === 'dual', $plan->stagingDomain),
                'production' => $stage(true, $plan->domain),
            ],
        ];
        DeploymentConfig::validateTopologyDocument($document);
        $this->context->writeFile('.accelerator/deploy.json', json_encode(
            $document,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        ).PHP_EOL);

        $runtime = file_get_contents($this->context->projectRoot.'/.env.example');

        if (! is_string($runtime)) {
            throw new RuntimeException('Unable to read generated .env.example.');
        }

        if ($plan->deploymentMode === 'dual') {
            $this->context->writeFile('.accelerator/environments/staging.env', $this->productionEnvironment(
                $runtime,
                'staging',
                $plan->stagingDomain,
                $plan->stagingDeployRoot,
                true,
            ), 0600);
        }

        $this->context->writeFile('.accelerator/environments/production.env', $this->productionEnvironment(
            $runtime,
            'production',
            $plan->domain,
            $plan->deployRoot,
            $plan->deploymentMode === 'dual',
        ), 0600);
    }

    private function renderEnvironment(string $template, string $appKey): string
    {
        $plan = $this->context->plan;
        $cacheDriver = $plan->useRedis ? 'redis' : 'database';
        $databaseName = str_replace('-', '_', $plan->deploymentKey);
        $values = [
            'APP_NAME' => '"'.addcslashes($plan->appName, '"\\').'"',
            'APP_KEY' => $appKey,
            'APP_URL' => $plan->appUrl,
            'DB_CONNECTION' => $plan->database,
            'CACHE_STORE' => $cacheDriver,
            'SESSION_DRIVER' => $cacheDriver,
            'SESSION_STORE' => $cacheDriver,
            'QUEUE_CONNECTION' => $cacheDriver,
            'BROADCAST_CONNECTION' => $this->context->hasFeature('reverb') ? 'reverb' : 'log',
            'SCOUT_DRIVER' => $this->context->hasFeature('scout') ? 'database' : 'collection',
            'NIGHTOWL_ENABLED' => $this->context->boolean($this->context->hasFeature('nightowl')),
            'NIGHTWATCH_ENABLED' => $this->context->boolean($this->context->hasFeature('nightowl')),
            'ACCELERATOR_FEATURE_OAUTH' => $this->context->boolean($this->context->hasFeature('oauth')),
            'ACCELERATOR_FEATURE_PWA' => $this->context->boolean($this->context->hasFeature('pwa')),
            'ACCELERATOR_FEATURE_TELEGRAM' => $this->context->boolean($this->context->hasFeature('telegram')),
            'ACCELERATOR_FEATURE_HORIZON' => $this->context->boolean($this->context->hasFeature('horizon')),
            'ACCELERATOR_FEATURE_REVERB' => $this->context->boolean($this->context->hasFeature('reverb')),
            'ACCELERATOR_FEATURE_SCOUT' => $this->context->boolean($this->context->hasFeature('scout')),
            'ACCELERATOR_FEATURE_NIGHTOWL' => $this->context->boolean($this->context->hasFeature('nightowl')),
            'ACCELERATOR_OAUTH_MODE' => $this->context->hasFeature('oauth') ? 'existing_only' : 'disabled',
            'ACCELERATOR_UPLOAD_MAX_MB' => '100',
            'ACCELERATOR_UI_DENSITY' => 'compact',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => $this->context->boolean(
                $this->context->plan->deploy && $this->context->plan->deploymentMode === 'dual',
            ),
            'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => $this->context->plan->deploy
                && $this->context->plan->deploymentMode === 'dual' ? '"LOCAL DATA"' : '',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => $this->context->plan->deploy
                && $this->context->plan->deploymentMode === 'dual' ? 'info' : 'warning',
            'ACCELERATOR_BACKUP_ENABLED' => 'true',
            'ACCELERATOR_BACKUP_NAME' => 'local',
            'ACCELERATOR_BACKUP_DISKS' => 'local',
            'ACCELERATOR_BACKUP_TIME' => '02:00',
            'ACCELERATOR_BACKUP_MAXIMUM_AGE_DAYS' => '2',
            'ACCELERATOR_BACKUP_MAXIMUM_STORAGE_MEGABYTES' => '5000',
            'ACCELERATOR_DEPLOYMENT_KEY' => 'local',
            'ACCELERATOR_DEPLOYMENT_STAGE' => 'local',
            'ACCELERATOR_DEPLOY_ROOT' => '',
            'ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES' => 'false',
            'GOOGLE_REDIRECT_URI' => rtrim($plan->appUrl, '/').'/auth/google/callback',
            'VITE_APP_NAME' => '"'.addcslashes($plan->appName, '"\\').'"',
        ];

        if ($this->context->hasFeature('reverb') && $appKey !== '') {
            $reverbKey = bin2hex(random_bytes(16));
            $values += [
                'REVERB_APP_ID' => bin2hex(random_bytes(8)),
                'REVERB_APP_KEY' => $reverbKey,
                'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
                'VITE_REVERB_APP_KEY' => $reverbKey,
            ];
        }

        if ($plan->database !== 'sqlite') {
            $values += [
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => $plan->database === 'pgsql' ? '5432' : '3306',
                'DB_DATABASE' => $databaseName,
                'DB_USERNAME' => $databaseName,
                'DB_PASSWORD' => '',
            ];
        }

        foreach ($values as $key => $value) {
            $template = $this->setEnvironmentValue($template, $key, $value);
        }

        return rtrim($template).PHP_EOL;
    }

    private function productionEnvironment(string $contents, string $stage, string $domain, string $deployRoot, bool $dualStage): string
    {
        $contents = $this->setEnvironmentValue($contents, 'APP_ENV', 'production');
        $contents = $this->setEnvironmentValue($contents, 'APP_KEY', 'base64:'.base64_encode(random_bytes(32)));
        $contents = $this->setEnvironmentValue($contents, 'APP_DEBUG', 'false');
        $contents = $this->setEnvironmentValue($contents, 'APP_URL', "https://{$domain}");
        $contents = $this->setEnvironmentValue($contents, 'LOG_LEVEL', 'error');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED', $this->context->boolean($dualStage));
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL', $stage === 'staging' ? '"TEST DATA"' : '"LIVE DATA"');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR', $stage === 'staging' ? 'warning' : 'danger');
        $prefix = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', "{$this->context->plan->deploymentKey}_{$stage}"));
        $contents = $this->setEnvironmentValue($contents, 'REDIS_PREFIX', "{$prefix}_database_");
        $contents = $this->setEnvironmentValue($contents, 'CACHE_PREFIX', "{$prefix}_cache_");
        $contents = $this->setEnvironmentValue($contents, 'HORIZON_NAME', "{$this->context->plan->deploymentKey}-{$stage}");
        $contents = $this->setEnvironmentValue($contents, 'HORIZON_PREFIX', "{$prefix}_horizon:");
        $contents = $this->setEnvironmentValue($contents, 'SESSION_COOKIE', "{$prefix}_session");
        $stagePortBase = $this->context->plan->portBase + ($stage === 'production' && $this->context->plan->deploymentMode === 'dual' ? 10 : 0);
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_DEPLOYMENT_KEY', $this->context->plan->deploymentKey);
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_DEPLOYMENT_STAGE', $stage);
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_DEPLOY_ROOT', $deployRoot);
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_ENABLED', 'true');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_NAME', "acc-{$this->context->plan->deploymentKey}-{$stage}");
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_DISKS', 'local');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_TIME', sprintf('02:%02d', $stagePortBase % 60));
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_MAXIMUM_AGE_DAYS', '2');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_BACKUP_MAXIMUM_STORAGE_MEGABYTES', '5000');
        $contents = $this->setEnvironmentValue($contents, 'ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES', 'false');
        $contents = $this->setEnvironmentValue($contents, 'OCTANE_PORT', (string) $stagePortBase);
        $contents = $this->setEnvironmentValue($contents, 'REVERB_SERVER_PORT', (string) ($stagePortBase + 1));
        $contents = $this->setEnvironmentValue($contents, 'NIGHTOWL_AGENT_PORT', (string) ($stagePortBase + 2));
        $contents = $this->setEnvironmentValue($contents, 'NIGHTOWL_INGEST_URI', '127.0.0.1:'.($stagePortBase + 2));
        $contents = $this->setEnvironmentValue($contents, 'NIGHTOWL_UDP_PORT', (string) ($stagePortBase + 3));
        $contents = $this->setEnvironmentValue($contents, 'NIGHTOWL_HEALTH_PORT', (string) ($stagePortBase + 4));

        if ($this->context->hasFeature('nightowl')) {
            $nightowlDatabase = "acc_nightowl_{$this->context->plan->deploymentKey}_{$stage}";

            foreach ([
                'NIGHTOWL_ENABLED' => 'true',
                'NIGHTOWL_PARALLEL_WITH_NIGHTWATCH' => 'false',
                'NIGHTOWL_AGENT_HOST' => '127.0.0.1',
                'NIGHTOWL_ENABLE_UDP' => 'false',
                'NIGHTOWL_HEALTH_ENABLED' => 'true',
                'NIGHTOWL_HEALTH_REPORT_ENABLED' => 'false',
                'NIGHTOWL_TABLE_STATS' => 'false',
                'NIGHTOWL_DB_CONNECTION' => 'pgsql',
                'NIGHTOWL_DB_HOST' => '127.0.0.1',
                'NIGHTOWL_DB_PORT' => '5432',
                'NIGHTOWL_DB_DATABASE' => $nightowlDatabase,
                'NIGHTOWL_DB_USERNAME' => $nightowlDatabase,
                'NIGHTOWL_DB_PASSWORD' => bin2hex(random_bytes(32)),
                'NIGHTOWL_AUTHENTICATED_REQUEST_SAMPLE_RATE' => '1.0',
                'NIGHTWATCH_ENABLED' => 'true',
                'NIGHTWATCH_LOG_LEVEL' => 'debug',
                'NIGHTWATCH_CAPTURE_REQUEST_PAYLOAD' => 'true',
                'NIGHTWATCH_CAPTURE_EXCEPTION_SOURCE_CODE' => 'true',
                'NIGHTWATCH_IGNORE_REQUEST_HEADERS' => 'false',
                'NIGHTWATCH_IGNORE_QUERIES' => 'false',
                'NIGHTWATCH_IGNORE_OUTGOING_REQUESTS' => 'false',
                'NIGHTWATCH_IGNORE_CACHE_EVENTS' => 'false',
                'NIGHTWATCH_IGNORE_MAIL' => 'false',
                'NIGHTWATCH_IGNORE_NOTIFICATIONS' => 'false',
                'NIGHTWATCH_REQUEST_SAMPLE_RATE' => '0.0',
                'NIGHTWATCH_EXCEPTION_SAMPLE_RATE' => '0.0',
                'NIGHTWATCH_COMMAND_SAMPLE_RATE' => '1.0',
                'NIGHTWATCH_SCHEDULED_TASK_SAMPLE_RATE' => '1.0',
            ] as $key => $value) {
                $contents = $this->setEnvironmentValue($contents, $key, $value);
            }
        }

        if ($this->context->plan->database === 'sqlite') {
            $contents = $this->setEnvironmentValue($contents, 'DB_DATABASE', rtrim($deployRoot, '/').'/shared/database/database.sqlite');
        } else {
            $database = str_replace('-', '_', $this->context->plan->deploymentKey).'_'.$stage;
            $contents = $this->setEnvironmentValue($contents, 'DB_DATABASE', $database);
        }

        if ($this->context->hasFeature('reverb')) {
            $reverbKey = bin2hex(random_bytes(16));

            foreach ([
                'REVERB_APP_ID' => bin2hex(random_bytes(8)),
                'REVERB_APP_KEY' => $reverbKey,
                'REVERB_APP_SECRET' => bin2hex(random_bytes(32)),
                'REVERB_HOST' => $domain,
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'VITE_REVERB_APP_KEY' => $reverbKey,
                'VITE_REVERB_HOST' => $domain,
                'VITE_REVERB_PORT' => '443',
                'VITE_REVERB_SCHEME' => 'https',
            ] as $key => $value) {
                $contents = $this->setEnvironmentValue($contents, $key, $value);
            }
        }

        foreach (['DB_PASSWORD', 'GOOGLE_CLIENT_SECRET', 'TELEGRAM_BOT_TOKEN', 'VAPID_PRIVATE_KEY'] as $key) {
            $contents = $this->setEnvironmentValue($contents, $key, '');
        }

        return $contents;
    }

    private function setEnvironmentValue(string $contents, string $key, string $value): string
    {
        $pattern = '/^(?:#\s*)?'.preg_quote($key, '/').'=.*$/m';
        $replacement = "{$key}={$value}";

        if (preg_match($pattern, $contents) === 1) {
            return (string) preg_replace_callback($pattern, static fn (): string => $replacement, $contents, 1);
        }

        return rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function environmentValue(string $contents, string $key): string
    {
        if (preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) !== 1) {
            return '';
        }

        return trim($matches[1], " \t\n\r\0\x0B\"");
    }

    private function updateGitignore(): void
    {
        $path = $this->context->projectRoot.'/.gitignore';
        $contents = is_file($path) ? file_get_contents($path) : '';

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read .gitignore.');
        }

        $contents = preg_replace('/^\/\.accelerator\/[ \t]*$\R?/m', '', $contents) ?? $contents;

        foreach (['/.accelerator/install-state.json', '/.accelerator/environments/', '/.accelerator/restore-state/', '/storage/framework/accelerator-backup-state.json'] as $entry) {
            if (! preg_match('/^'.preg_quote($entry, '/').'$/m', $contents)) {
                $contents = rtrim($contents).PHP_EOL.$entry.PHP_EOL;
            }
        }

        $this->context->writeFile('.gitignore', $contents);
    }
}

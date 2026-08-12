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

        $current = file_get_contents($this->context->projectRoot.'/.env');
        $appKey = is_string($current) ? $this->environmentValue($current, 'APP_KEY') : '';
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
        $stage = static fn (bool $enabled, string $domain): array => [
            'enabled' => $enabled,
            'ssh_host' => $plan->sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'health_path' => '/up',
        ];
        $document = [
            'schema' => 3,
            'default_stage' => $plan->deploymentMode === 'dual' ? 'staging' : 'production',
            'deployment_key' => $plan->deploymentKey,
            'repository' => $plan->repository,
            'branch' => $plan->repositoryBranch,
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
        $this->context->writeFile('.accelerator/deploy.json', json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);

        $runtime = file_get_contents($this->context->projectRoot.'/.env.example');

        if (! is_string($runtime)) {
            throw new RuntimeException('Unable to read generated .env.example.');
        }

        if ($plan->deploymentMode === 'dual') {
            $this->context->writeFile('.accelerator/environments/staging.env', $this->productionEnvironment($runtime, 'staging', $plan->stagingDomain, $plan->stagingDeployRoot, true), 0600);
        }

        $this->context->writeFile('.accelerator/environments/production.env', $this->productionEnvironment($runtime, 'production', $plan->domain, $plan->deployRoot, $plan->deploymentMode === 'dual'), 0600);
    }

    private function renderEnvironment(string $template, string $appKey): string
    {
        $plan = $this->context->plan;
        $cacheDriver = $plan->useRedis ? 'redis' : 'database';
        $values = [
            'APP_NAME' => '"'.addcslashes($plan->appName, '"\\').'"',
            'APP_KEY' => $appKey,
            'APP_URL' => $plan->appUrl,
            'DB_CONNECTION' => $plan->database,
            'CACHE_STORE' => $cacheDriver,
            'SESSION_DRIVER' => $cacheDriver,
            'SESSION_STORE' => $cacheDriver,
            'QUEUE_CONNECTION' => 'database',
            'BROADCAST_CONNECTION' => $this->context->hasFeature('realtime') ? 'reverb' : 'log',
            'SCOUT_DRIVER' => $this->context->hasFeature('scout') ? 'database' : 'collection',
            'LOG_STACK' => $this->context->hasFeature('observability') ? 'daily,otlp' : 'daily',
            'OTEL_SDK_DISABLED' => $this->context->boolean(! $this->context->hasFeature('observability')),
            'OTEL_INSTRUMENTATION_HTTP_SERVER' => 'false',
            'ACCELERATOR_FEATURE_OAUTH' => $this->context->boolean($this->context->hasFeature('oauth')),
            'ACCELERATOR_FEATURE_PWA' => $this->context->boolean($this->context->hasFeature('pwa')),
            'ACCELERATOR_FEATURE_TELEGRAM' => $this->context->boolean($this->context->hasFeature('telegram')),
            'ACCELERATOR_FEATURE_REALTIME' => $this->context->boolean($this->context->hasFeature('realtime')),
            'ACCELERATOR_FEATURE_SCOUT' => $this->context->boolean($this->context->hasFeature('scout')),
            'ACCELERATOR_FEATURE_OBSERVABILITY' => $this->context->boolean($this->context->hasFeature('observability')),
            'ACCELERATOR_OAUTH_MODE' => $this->context->hasFeature('oauth') ? 'existing_only' : 'disabled',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => $this->context->boolean($plan->deploy && $plan->deploymentMode === 'dual'),
            'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => $plan->deploy && $plan->deploymentMode === 'dual' ? '"LOCAL DATA"' : '',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => $plan->deploy && $plan->deploymentMode === 'dual' ? 'info' : 'warning',
            'GOOGLE_REDIRECT_URI' => rtrim($plan->appUrl, '/').'/auth/google/callback',
            'VITE_APP_NAME' => '"'.addcslashes($plan->appName, '"\\').'"',
        ];

        if ($this->context->hasFeature('realtime')) {
            $values += [
                'REVERB_APP_ID' => $plan->reverbAppId,
                'REVERB_APP_KEY' => $plan->reverbAppKey,
                'REVERB_APP_SECRET' => $plan->reverbAppSecret,
                'VITE_REVERB_APP_KEY' => $plan->reverbAppKey,
                'REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'VITE_REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
                'VITE_REVERB_PORT' => '443',
                'VITE_REVERB_SCHEME' => 'https',
            ];
        }

        if ($plan->database !== 'sqlite') {
            $databaseName = str_replace('-', '_', $plan->deploymentKey);
            $values += ['DB_HOST' => '127.0.0.1', 'DB_PORT' => $plan->database === 'pgsql' ? '5432' : '3306', 'DB_DATABASE' => $databaseName, 'DB_USERNAME' => $databaseName, 'DB_PASSWORD' => ''];
        }

        foreach ($values as $key => $value) {
            $template = $this->setEnvironmentValue($template, $key, $value);
        }

        return rtrim($template).PHP_EOL;
    }

    private function productionEnvironment(string $contents, string $stage, string $domain, string $deployRoot, bool $dualStage): string
    {
        $deploymentKey = $this->context->plan->deploymentKey;
        $prefix = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', "{$deploymentKey}_{$stage}"));
        $values = [
            'APP_ENV' => 'production', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'APP_DEBUG' => 'false', 'APP_URL' => "https://{$domain}",
            'LOG_LEVEL' => 'error', 'QUEUE_CONNECTION' => 'database', 'SESSION_COOKIE' => "{$prefix}_session", 'REDIS_PREFIX' => "{$prefix}_database_", 'CACHE_PREFIX' => "{$prefix}_cache_",
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => $this->context->boolean($dualStage), 'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => $stage === 'staging' ? '"TEST DATA"' : '"LIVE DATA"', 'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => $stage === 'staging' ? 'warning' : 'danger',
            'ACCELERATOR_DEPLOYMENT_KEY' => $deploymentKey, 'ACCELERATOR_DEPLOYMENT_STAGE' => $stage, 'ACCELERATOR_DEPLOY_ROOT' => $deployRoot,
            'ACCELERATOR_BACKUP_NAME' => "acc-{$deploymentKey}-{$stage}", 'ACCELERATOR_BACKUP_S3_ENABLED' => 'true', 'ACCELERATOR_BACKUP_TIME' => $stage === 'staging' ? '02:10' : '02:20',
            'OTEL_SERVICE_NAME' => $deploymentKey, 'OTEL_SERVICE_INSTANCE_ID' => "{$deploymentKey}-{$stage}",
            'OTEL_RESOURCE_ATTRIBUTES' => "\"service.namespace=accelerator,deployment.environment.name={$stage},service.instance.id={$deploymentKey}-{$stage}\"",
            'OTEL_EXPORTER_OTLP_ENDPOINT' => 'https://observe.ohmyserver.com/api/default', 'OTEL_EXPORTER_OTLP_HEADERS' => '', 'OTEL_EXPORTER_OTLP_PROTOCOL' => 'http/protobuf',
        ];

        if ($this->context->plan->database === 'sqlite') {
            $values['DB_DATABASE'] = rtrim($deployRoot, '/').'/shared/database/database.sqlite';
        } else {
            $values['DB_DATABASE'] = str_replace('-', '_', $deploymentKey).'_'.$stage;
        }

        if ($this->context->hasFeature('realtime')) {
            $values += [
                'REVERB_APP_ID' => $this->context->plan->reverbAppId, 'REVERB_APP_KEY' => $this->context->plan->reverbAppKey, 'REVERB_APP_SECRET' => $this->context->plan->reverbAppSecret,
                'REVERB_HOST' => 'centralized-reverb.ohmyserver.com', 'REVERB_PORT' => '443', 'REVERB_SCHEME' => 'https',
                'VITE_REVERB_APP_KEY' => $this->context->plan->reverbAppKey, 'VITE_REVERB_HOST' => 'centralized-reverb.ohmyserver.com', 'VITE_REVERB_PORT' => '443', 'VITE_REVERB_SCHEME' => 'https',
            ];
        }

        foreach ($values as $key => $value) {
            $contents = $this->setEnvironmentValue($contents, $key, $value);
        }

        foreach (['DB_PASSWORD', 'GOOGLE_CLIENT_SECRET', 'TELEGRAM_BOT_TOKEN', 'VAPID_PRIVATE_KEY', 'OTEL_EXPORTER_OTLP_HEADERS'] as $key) {
            $contents = $this->setEnvironmentValue($contents, $key, '');
        }

        return $contents;
    }

    private function setEnvironmentValue(string $contents, string $key, string $value): string
    {
        $pattern = '/^(?:#\s*)?'.preg_quote($key, '/').'=.*$/m';
        $replacement = "{$key}={$value}";

        return preg_match($pattern, $contents) === 1
            ? (string) preg_replace_callback($pattern, static fn (): string => $replacement, $contents, 1)
            : rtrim($contents).PHP_EOL.$replacement.PHP_EOL;
    }

    private function environmentValue(string $contents, string $key): string
    {
        return preg_match('/^'.preg_quote($key, '/').'=(.*)$/m', $contents, $matches) === 1
            ? trim($matches[1], " \t\n\r\0\x0B\"")
            : '';
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

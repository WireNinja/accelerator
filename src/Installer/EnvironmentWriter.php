<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Installer;

use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use WireNinja\Accelerator\Configuration\ReverbApplicationRegistry;

final readonly class EnvironmentWriter
{
    public function __construct(private InstallContext $context) {}

    /** @throws JsonException */
    public function write(): void
    {
        $this->writeEnvironmentFiles();
        $this->writeReverbRegistry();
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

    private function renderEnvironment(string $template, string $appKey): string
    {
        $plan = $this->context->plan;
        $cacheDriver = $plan->useRedis ? 'redis' : 'database';
        $deploymentKey = Str::slug($plan->appName);
        $serviceName = "{$deploymentKey}-local";
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
            'ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED' => 'false',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL' => '',
            'ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR' => 'warning',
            'GOOGLE_REDIRECT_URI' => rtrim($plan->appUrl, '/').'/auth/google/callback',
            'VITE_APP_NAME' => '"'.addcslashes($plan->appName, '"\\').'"',
            'OTEL_SERVICE_NAME' => $serviceName,
            'OTEL_SERVICE_INSTANCE_ID' => $serviceName,
            'OTEL_RESOURCE_ATTRIBUTES' => "\"service.namespace=accelerator,deployment.environment.name=local,service.instance.id={$serviceName}\"",
        ];

        if ($this->context->hasFeature('realtime')) {
            $reverb = $plan->reverbApplications['local'];
            $values += [
                'REVERB_APP_ID' => $reverb['app_id'],
                'REVERB_APP_KEY' => $reverb['key'],
                'REVERB_APP_SECRET' => $reverb['secret'],
                'VITE_REVERB_APP_KEY' => $reverb['key'],
                'REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
                'REVERB_PORT' => '443',
                'REVERB_SCHEME' => 'https',
                'VITE_REVERB_HOST' => 'centralized-reverb.ohmyserver.com',
                'VITE_REVERB_PORT' => '443',
                'VITE_REVERB_SCHEME' => 'https',
            ];
        }

        if ($plan->database !== 'sqlite') {
            $databaseName = str_replace('-', '_', $deploymentKey);
            $values += ['DB_HOST' => '127.0.0.1', 'DB_PORT' => $plan->database === 'pgsql' ? '5432' : '3306', 'DB_DATABASE' => $databaseName, 'DB_USERNAME' => $databaseName, 'DB_PASSWORD' => ''];
        }

        foreach ($values as $key => $value) {
            $template = $this->setEnvironmentValue($template, $key, $value);
        }

        return rtrim($template).PHP_EOL;
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

    /** @throws JsonException */
    private function writeReverbRegistry(): void
    {
        if (! $this->context->hasFeature('realtime')) {
            return;
        }

        $registry = new ReverbApplicationRegistry($this->context->projectRoot);
        $registry->write(array_values($this->context->plan->reverbApplications));
    }

    private function updateGitignore(): void
    {
        $path = $this->context->projectRoot.'/.gitignore';
        $contents = is_file($path) ? file_get_contents($path) : '';

        if (! is_string($contents)) {
            throw new RuntimeException('Unable to read .gitignore.');
        }

        $contents = preg_replace('/^\/\.accelerator\/[ \t]*$\R?/m', '', $contents) ?? $contents;

        foreach (['/.accelerator/install-state.json', '/.accelerator/reverb-apps.json', '/storage/framework/accelerator-backup-state.json'] as $entry) {
            if (! preg_match('/^'.preg_quote($entry, '/').'$/m', $contents)) {
                $contents = rtrim($contents).PHP_EOL.$entry.PHP_EOL;
            }
        }

        $this->context->writeFile('.gitignore', $contents);
    }
}

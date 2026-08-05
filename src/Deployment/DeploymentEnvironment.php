<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;
use WireNinja\Accelerator\Configuration\EnvironmentStore;

final readonly class DeploymentEnvironment
{
    public function __construct(private string $projectRoot) {}

    /** @return array<string, string> */
    public function read(DeploymentConfig $config): array
    {
        $relative = ".accelerator/environments/{$config->stage}.env";
        $values = (new EnvironmentStore($this->projectRoot))->read($relative);

        if ($values === []) {
            throw new RuntimeException("Stage environment [{$relative}] is missing or empty.");
        }

        return $values;
    }

    /** @return list<string> */
    public function validate(DeploymentConfig $config, bool $requireExternalSecrets = true): array
    {
        $values = $this->read($config);
        $prefix = str_replace('-', '_', "{$config->deploymentKey}_{$config->stage}");
        $expected = [
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => "https://{$config->domain}",
            'REDIS_PREFIX' => "{$prefix}_database_",
            'CACHE_PREFIX' => "{$prefix}_cache_",
            'HORIZON_NAME' => "{$config->deploymentKey}-{$config->stage}",
            'HORIZON_PREFIX' => "{$prefix}_horizon:",
            'SESSION_COOKIE' => "{$prefix}_session",
            'OCTANE_PORT' => (string) $config->octanePort,
            'REVERB_SERVER_PORT' => (string) $config->reverbPort,
            'NIGHTOWL_AGENT_HOST' => '127.0.0.1',
            'NIGHTOWL_AGENT_PORT' => (string) $config->nightowlPort,
            'NIGHTOWL_INGEST_URI' => "127.0.0.1:{$config->nightowlPort}",
            'NIGHTOWL_UDP_PORT' => (string) $config->nightowlUdpPort,
            'NIGHTOWL_HEALTH_PORT' => (string) $config->nightowlHealthPort,
            'NIGHTOWL_PARALLEL_WITH_NIGHTWATCH' => 'false',
            'NIGHTWATCH_REQUEST_SAMPLE_RATE' => '0.0',
            'NIGHTWATCH_EXCEPTION_SAMPLE_RATE' => '0.0',
            'ACCELERATOR_FEATURE_HORIZON' => $config->horizonEnabled ? 'true' : 'false',
            'ACCELERATOR_FEATURE_REVERB' => $config->reverbEnabled ? 'true' : 'false',
            'ACCELERATOR_FEATURE_NIGHTOWL' => $config->nightowlEnabled ? 'true' : 'false',
        ];
        $errors = [];

        foreach ($expected as $key => $value) {
            if (($values[$key] ?? null) !== $value) {
                $errors[] = "{$key} must equal the stage-derived value.";
            }
        }

        $key = $values['APP_KEY'] ?? '';
        $decoded = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if (! is_string($decoded) || strlen($decoded) !== 32) {
            $errors[] = 'APP_KEY must be a valid 32-byte base64 Laravel key.';
        }

        foreach (['DB_CONNECTION', 'DB_DATABASE'] as $required) {
            if (($values[$required] ?? '') === '') {
                $errors[] = "{$required} is required.";
            }
        }

        if ($config->nightowlEnabled) {
            $database = $config->nightowlDatabaseName();

            foreach (['NIGHTOWL_DB_DATABASE', 'NIGHTOWL_DB_USERNAME'] as $key) {
                if (($values[$key] ?? '') !== $database) {
                    $errors[] = "{$key} must equal the deterministic app-stage identity.";
                }
            }

            if (($values['NIGHTOWL_DB_CONNECTION'] ?? '') !== 'pgsql') {
                $errors[] = 'NIGHTOWL_DB_CONNECTION must equal pgsql.';
            }

            if (! in_array($values['NIGHTOWL_DB_HOST'] ?? '', ['127.0.0.1', 'localhost', '::1'], true)) {
                $errors[] = 'NIGHTOWL_DB_HOST must target the local self-hosted PostgreSQL server.';
            }

            if ($requireExternalSecrets && ($values['NIGHTOWL_DB_PASSWORD'] ?? '') === '') {
                $errors[] = 'NIGHTOWL_DB_PASSWORD is required while NightOwl is enabled.';
            }
        }

        return $errors;
    }

    public function assertValid(DeploymentConfig $config): void
    {
        $errors = $this->validate($config);

        if ($errors !== []) {
            throw new RuntimeException(implode(PHP_EOL, $errors));
        }
    }
}

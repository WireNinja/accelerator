<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Deployment;

use RuntimeException;
use WireNinja\Accelerator\Configuration\EnvironmentStore;

final class LegacyDeploymentConfig
{
    /** @return array<string, mixed> */
    public static function read(string $projectRoot, string $packageManager): array
    {
        $values = (new EnvironmentStore($projectRoot))->read('.accelerator/deploy.env');

        if ($values === []) {
            throw new RuntimeException('Legacy .accelerator/deploy.env is missing or empty.');
        }

        $sshHost = self::value($values, 'OPS_DEPLOY_SSH_HOST');
        $project = self::value($values, 'OPS_DEPLOY_PROJECT');
        $staging = self::stage($values, 'STAGING', $sshHost, $project);
        $production = self::stage($values, 'PRODUCTION', $sshHost, $project);
        $document = [
            'schema' => 1,
            'default_stage' => self::value($values, 'OPS_DEPLOY_DEFAULT_STAGE', 'production'),
            'project' => $project,
            'repository' => self::value($values, 'OPS_DEPLOY_REPO'),
            'branch' => self::value($values, 'OPS_DEPLOY_BRANCH', 'main'),
            'keep_releases' => self::integer($values, 'OPS_DEPLOY_KEEP_RELEASES', 5),
            'php_version' => self::value($values, 'OPS_DEPLOY_PHP_VERSION', '8.5'),
            'php_binary' => self::value($values, 'OPS_DEPLOY_PHP_BIN', 'php8.5'),
            'package_manager' => $packageManager,
            'run_user' => self::value($values, 'OPS_DEPLOY_RUN_USER', 'www-data'),
            'ssl_email' => self::value($values, 'OPS_DEPLOY_SSL_EMAIL'),
            'stages' => [
                'staging' => $staging,
                'production' => $production,
            ],
        ];

        DeploymentConfig::validateInstallerTargets(
            project: (string) $document['project'],
            sshHost: $sshHost,
            repository: (string) $document['repository'],
            branch: (string) $document['branch'],
            productionDomain: (string) $production['domain'],
            productionRoot: (string) $production['root'],
            stagingDomain: $staging['enabled'] ? (string) $staging['domain'] : null,
            stagingRoot: $staging['enabled'] ? (string) $staging['root'] : null,
        );
        DeploymentConfig::validateTopologyDocument($document);

        return $document;
    }

    /** @param array<string, string> $values @return array<string, bool|int|string> */
    private static function stage(array $values, string $stage, string $sshHost, string $project): array
    {
        $prefix = "OPS_DEPLOY_{$stage}_";
        $domain = self::value($values, $prefix.'DOMAIN');

        return [
            'enabled' => self::boolean($values, $prefix.'ENABLED', $stage === 'PRODUCTION'),
            'ssh_host' => $sshHost,
            'domain' => $domain,
            'root' => $domain === '' ? '' : "/var/www/{$domain}",
            'service_group' => self::value($values, $prefix.'GROUP', DeploymentConfig::defaultServiceGroup($project, strtolower($stage))),
            'dns_direct' => self::boolean($values, $prefix.'DNS_DIRECT', true),
            'http_runtime' => self::value($values, $prefix.'HTTP_RUNTIME', 'octane'),
            'fpm_socket' => self::value($values, $prefix.'FPM_SOCKET'),
            'fpm_service' => self::value($values, $prefix.'FPM_SERVICE'),
            'octane_server' => self::value($values, $prefix.'OCTANE_SERVER', 'swoole'),
            'octane_port' => self::integer($values, $prefix.'OCTANE_PORT', $stage === 'PRODUCTION' ? 8000 : 8100),
            'octane_workers' => self::integer($values, $prefix.'OCTANE_WORKERS', 4),
            'octane_task_workers' => self::integer($values, $prefix.'OCTANE_TASK_WORKERS', 2),
            'horizon' => self::boolean($values, $prefix.'HORIZON_ENABLED'),
            'queue_worker' => self::boolean($values, $prefix.'QUEUE_WORKER_ENABLED'),
            'queue_connection' => self::value($values, $prefix.'QUEUE_WORKER_CONNECTION', 'database'),
            'queue' => self::value($values, $prefix.'QUEUE_WORKER_QUEUE', 'default'),
            'queue_processes' => self::integer($values, $prefix.'QUEUE_WORKER_PROCESSES', 1),
            'reverb' => self::boolean($values, $prefix.'REVERB_ENABLED'),
            'reverb_port' => self::integer($values, $prefix.'REVERB_PORT', $stage === 'PRODUCTION' ? 8080 : 8180),
            'scheduler' => self::boolean($values, $prefix.'SCHEDULER_ENABLED', true),
            'nightwatch' => self::boolean($values, $prefix.'NIGHTWATCH_ENABLED'),
            'nightwatch_port' => self::integer($values, $prefix.'NIGHTWATCH_PORT', $stage === 'PRODUCTION' ? 2407 : 2507),
            'health_path' => '/up',
        ];
    }

    /** @param array<string, string> $values */
    private static function value(array $values, string $key, string $default = ''): string
    {
        return trim($values[$key] ?? $default);
    }

    /** @param array<string, string> $values */
    private static function boolean(array $values, string $key, bool $default = false): bool
    {
        return filter_var($values[$key] ?? $default, FILTER_VALIDATE_BOOL);
    }

    /** @param array<string, string> $values */
    private static function integer(array $values, string $key, int $default): int
    {
        $value = filter_var($values[$key] ?? $default, FILTER_VALIDATE_INT);

        return is_int($value) && $value > 0 ? $value : $default;
    }
}

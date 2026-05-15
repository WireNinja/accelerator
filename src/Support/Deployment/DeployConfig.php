<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Support\Deployment;

use InvalidArgumentException;

final class DeployConfig
{
    /**
     * @return array<string, mixed>
     */
    public static function stage(?string $stage = null): array
    {
        $stageName = self::stageName($stage);
        $stages = (array) config('accelerator.deploy.stages', []);
        $config = (array) ($stages[$stageName] ?? []);

        if ($config === []) {
            throw new InvalidArgumentException("Deployment stage [{$stageName}] is not configured.");
        }

        if (! self::truthy($config['enabled'] ?? true)) {
            throw new InvalidArgumentException("Deployment stage [{$stageName}] is disabled.");
        }

        $config['name'] = $stageName;
        $config['project'] = self::projectName();
        $config['root'] = rtrim((string) ($config['root'] ?? ''), '/');
        $config['group'] = (string) ($config['group'] ?: self::supervisorGroup($config));
        $config['paths'] = self::paths($config['root']);
        $config['services_raw'] = (array) ($config['services'] ?? []);
        $config['services'] = self::enabledServices($config['services_raw']);

        return $config;
    }

    public static function stageName(?string $stage = null): string
    {
        if (is_string($stage) && $stage !== '') {
            return $stage;
        }

        $default = config('accelerator.deploy.default_stage');

        if (is_string($default) && $default !== '') {
            return $default;
        }

        $stages = array_keys((array) config('accelerator.deploy.stages', []));

        if (count($stages) === 1) {
            return (string) $stages[0];
        }

        throw new InvalidArgumentException('Multiple deployment stages are configured. Pass --stage explicitly.');
    }

    /**
     * @return list<string>
     */
    public static function validate(array $stage): array
    {
        $errors = [];

        foreach (['domain', 'root', 'repo', 'branch', 'group', 'php_bin', 'bun_bin'] as $key) {
            if (! is_string($stage[$key] ?? null) || trim((string) $stage[$key]) === '') {
                $errors[] = "Missing deployment config [{$key}] for stage [{$stage['name']}].";
            }
        }

        if (($stage['ssl']['enabled'] ?? false) && empty($stage['ssl']['email'])) {
            $errors[] = "Missing SSL email for stage [{$stage['name']}].";
        }

        if (in_array('nightwatch', $stage['services'], true)) {
            $nightwatch = (array) ($stage['services_raw']['nightwatch'] ?? $stage['services']['nightwatch'] ?? []);
            $port = (int) (($stage['ports']['nightwatch'] ?? null) ?: ($nightwatch['port'] ?? 0));

            if ($port <= 0) {
                $errors[] = "Nightwatch is enabled for stage [{$stage['name']}] but no listen port is configured.";
            }
        }

        return $errors;
    }

    /**
     * @return array{root: string, current: string, releases: string, shared: string, archive: string}
     */
    public static function paths(string $root): array
    {
        return [
            'root' => $root,
            'current' => "{$root}/current",
            'releases' => "{$root}/releases",
            'shared' => "{$root}/shared",
            'archive' => "{$root}/archive",
        ];
    }

    public static function releaseId(string $sha): string
    {
        return now()->format('Y-m-d_H-i-s').'_'.substr($sha, 0, 7);
    }

    public static function programName(array $stage, string $service): string
    {
        return "{$stage['group']}_{$service}";
    }

    public static function projectName(): string
    {
        $name = (string) config('accelerator.deploy.project', config('app.name', 'laravel'));

        return str($name)
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '_')
            ->trim('_')
            ->value() ?: 'laravel';
    }

    public static function supervisorGroup(array $stage): string
    {
        return self::projectName().'_'.$stage['name'];
    }

    /**
     * @return list<string>
     */
    private static function enabledServices(array $services): array
    {
        return collect($services)
            ->filter(fn (mixed $service): bool => self::truthy(((array) $service)['enabled'] ?? false))
            ->keys()
            ->values()
            ->all();
    }

    public static function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}

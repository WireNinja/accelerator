<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use JsonException;
use WireNinja\Accelerator\Configuration\FeatureRegistry;
use WireNinja\Accelerator\Providers\OAuthServiceProvider;
use WireNinja\Accelerator\Providers\PwaServiceProvider;

final class FeatureListCommand extends Command
{
    protected $signature = 'accelerator:feature:list {--json : Output as JSON}';

    protected $description = 'List optional Accelerator features and their loaded runtime state';

    /** @throws JsonException */
    public function handle(): int
    {
        $features = array_map(
            fn (string $feature): array => $this->feature($feature),
            FeatureRegistry::names(),
        );
        $mismatches = array_values(array_filter(
            $features,
            static fn (array $feature): bool => $feature['enabled'] !== $feature['runtime_loaded'],
        ));
        $backup = $this->backupCapability();

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'schema' => 1,
                'status' => $mismatches === [] ? 'OK' : 'MISMATCH',
                'features' => $features,
                'backup' => $backup,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

            return $mismatches === [] ? self::SUCCESS : self::FAILURE;
        }

        $this->table(['Feature', 'Configured', 'Runtime', 'Source'], array_map(
            static fn (array $feature): array => [
                $feature['name'],
                $feature['enabled'] ? 'enabled' : 'disabled',
                $feature['runtime_loaded'] ? 'loaded' : 'unloaded',
                $feature['source'],
            ],
            $features,
        ));
        $this->table(['Backup capability', 'State'], [
            ['Engine installed', $backup['engine_installed'] ? 'yes' : 'no'],
            ['Schedule enabled', $backup['scheduled'] ? 'yes' : 'no'],
            ['Offsite destination', $backup['offsite_destination'] ? 'yes' : 'no'],
            ['Operator Telegram', $backup['operator_telegram'] ? 'configured' : 'not configured'],
            ['Last backup health', $backup['last_backup_health']],
        ]);

        return $mismatches === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{name: string, enabled: bool, runtime_loaded: bool, source: string} */
    private function feature(string $feature): array
    {
        $enabled = (bool) config("accelerator.features.{$feature}", false);
        [$runtimeLoaded, $source] = match ($feature) {
            'oauth' => [$this->providerLoaded(OAuthServiceProvider::class), OAuthServiceProvider::class],
            'pwa' => [$this->providerLoaded(PwaServiceProvider::class), PwaServiceProvider::class],
            'realtime' => [config('broadcasting.default') === 'reverb', 'broadcasting.default'],
            'scout' => [config('scout.driver') !== 'collection', 'scout.driver'],
            'observability' => [! (bool) config('opentelemetry.disabled', true), 'opentelemetry.disabled'],
            default => [$enabled, 'accelerator.features.telegram'],
        };

        return [
            'name' => $feature,
            'enabled' => $enabled,
            'runtime_loaded' => $runtimeLoaded,
            'source' => $source,
        ];
    }

    /** @param class-string $provider */
    private function providerLoaded(string $provider): bool
    {
        return app()->getProvider($provider) !== null;
    }

    /** @return array{engine_installed: bool, scheduled: bool, offsite_destination: bool, operator_telegram: bool, last_backup_health: string} */
    private function backupCapability(): array
    {
        $disks = (array) config('accelerator.backup.disks', ['local']);
        $statePath = storage_path('framework/accelerator-backup-state.json');
        $state = is_file($statePath) ? json_decode((string) file_get_contents($statePath), true) : null;
        $lastResult = is_array($state) && is_array($state['last_success'] ?? null) ? 'healthy' : 'unknown';

        if (is_array($state) && is_array($state['last_failure'] ?? null)) {
            $successAt = is_array($state['last_success'] ?? null) ? (string) ($state['last_success']['occurred_at'] ?? '') : '';
            $failureAt = (string) ($state['last_failure']['occurred_at'] ?? '');
            $lastResult = $failureAt > $successAt ? 'unhealthy' : $lastResult;
        }

        return [
            'engine_installed' => InstalledVersions::isInstalled('spatie/laravel-backup'),
            'scheduled' => (bool) config('accelerator.backup.enabled', true),
            'offsite_destination' => array_values(array_diff($disks, ['local'])) !== [],
            'operator_telegram' => filled(config('accelerator.operations.telegram.bot_token'))
                && filled(config('accelerator.operations.telegram.chat_id')),
            'last_backup_health' => $lastResult,
        ];
    }
}

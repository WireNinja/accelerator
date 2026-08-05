<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Console;

use Illuminate\Console\Command;
use JsonException;
use WireNinja\Accelerator\Providers\HorizonServiceProvider;
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
            ['oauth', 'pwa', 'telegram', 'horizon', 'reverb', 'scout', 'nightowl'],
        );
        $mismatches = array_values(array_filter(
            $features,
            static fn (array $feature): bool => $feature['enabled'] !== $feature['runtime_loaded'],
        ));

        if ($this->option('json')) {
            $this->output->writeln(json_encode([
                'schema' => 1,
                'status' => $mismatches === [] ? 'OK' : 'MISMATCH',
                'features' => $features,
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

        return $mismatches === [] ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{name: string, enabled: bool, runtime_loaded: bool, source: string} */
    private function feature(string $feature): array
    {
        $enabled = (bool) config("accelerator.features.{$feature}", false);
        [$runtimeLoaded, $source] = match ($feature) {
            'oauth' => [$this->providerLoaded(OAuthServiceProvider::class), OAuthServiceProvider::class],
            'pwa' => [$this->providerLoaded(PwaServiceProvider::class), PwaServiceProvider::class],
            'horizon' => [$this->providerLoaded(HorizonServiceProvider::class), HorizonServiceProvider::class],
            'reverb' => [config('broadcasting.default') === 'reverb', 'broadcasting.default'],
            'scout' => [config('scout.driver') !== 'collection', 'scout.driver'],
            'nightowl' => [
                (bool) config('nightowl.enabled', false) && ! (bool) config('nightowl.parallel_with_nightwatch', true),
                'nightowl.enabled + nightowl.parallel_with_nightwatch=false',
            ],
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
}

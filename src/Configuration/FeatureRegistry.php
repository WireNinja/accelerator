<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Configuration;

use Illuminate\Support\ServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;
use WireNinja\Accelerator\Providers\OAuthServiceProvider;
use WireNinja\Accelerator\Providers\PwaServiceProvider;

final class FeatureRegistry
{
    /**
     * @var array<string, array{label: string, environment_key: string, selected_by_default: bool, provider: class-string<ServiceProvider>|null}>
     */
    private const DEFINITIONS = [
        'oauth' => [
            'label' => 'Google OAuth (safe default: existing users only)',
            'environment_key' => 'ACCELERATOR_FEATURE_OAUTH',
            'selected_by_default' => false,
            'provider' => OAuthServiceProvider::class,
        ],
        'pwa' => [
            'label' => 'Progressive Web App assets',
            'environment_key' => 'ACCELERATOR_FEATURE_PWA',
            'selected_by_default' => true,
            'provider' => PwaServiceProvider::class,
        ],
        'telegram' => [
            'label' => 'Telegram notification channel',
            'environment_key' => 'ACCELERATOR_FEATURE_TELEGRAM',
            'selected_by_default' => false,
            'provider' => null,
        ],
        'realtime' => [
            'label' => 'Realtime broadcasting through centralized Reverb',
            'environment_key' => 'ACCELERATOR_FEATURE_REALTIME',
            'selected_by_default' => false,
            'provider' => null,
        ],
        'scout' => [
            'label' => 'Scout search with the database driver',
            'environment_key' => 'ACCELERATOR_FEATURE_SCOUT',
            'selected_by_default' => true,
            'provider' => null,
        ],
        'observability' => [
            'label' => 'OpenTelemetry export to centralized OpenObserve',
            'environment_key' => 'ACCELERATOR_FEATURE_OBSERVABILITY',
            'selected_by_default' => false,
            'provider' => LaravelOpenTelemetryServiceProvider::class,
        ],
    ];

    /** @return list<string> */
    public static function names(): array
    {
        return array_keys(self::DEFINITIONS);
    }

    /** @return array<string, string> */
    public static function labels(): array
    {
        return array_map(
            static fn (array $definition): string => $definition['label'],
            self::DEFINITIONS,
        );
    }

    /** @return list<string> */
    public static function defaults(): array
    {
        return array_keys(array_filter(
            self::DEFINITIONS,
            static fn (array $definition): bool => $definition['selected_by_default'],
        ));
    }

    /** @return array<string, string> */
    public static function environmentKeys(): array
    {
        return array_map(
            static fn (array $definition): string => $definition['environment_key'],
            self::DEFINITIONS,
        );
    }

    /** @return array<string, class-string<ServiceProvider>> */
    public static function providers(): array
    {
        $providers = [];

        foreach (self::DEFINITIONS as $feature => $definition) {
            if ($definition['provider'] !== null) {
                $providers[$feature] = $definition['provider'];
            }
        }

        return $providers;
    }
}

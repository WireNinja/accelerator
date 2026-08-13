<?php

namespace WireNinja\Accelerator;

use Illuminate\Support\ServiceProvider;
use Keepsuit\LaravelOpenTelemetry\LaravelOpenTelemetryServiceProvider;
use Override;
use WireNinja\Accelerator\Providers\CoreServiceProvider;
use WireNinja\Accelerator\Providers\FilamentServiceProvider;
use WireNinja\Accelerator\Providers\HeadServiceProvider;
use WireNinja\Accelerator\Providers\OAuthServiceProvider;
use WireNinja\Accelerator\Providers\PanelServiceProvider;
use WireNinja\Accelerator\Providers\PwaServiceProvider;
use WireNinja\Accelerator\Support\Filament\ShieldPermissions;

class AcceleratorServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<ServiceProvider>>
     */
    private const FEATURE_PROVIDERS = [
        'oauth' => OAuthServiceProvider::class,
        'pwa' => PwaServiceProvider::class,
        'observability' => LaravelOpenTelemetryServiceProvider::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accelerator.php', 'accelerator');
        $this->configureUnpublishedShieldDefaults();

        $this->app->register(CoreServiceProvider::class);
        $this->app->register(FilamentServiceProvider::class);
        $this->app->register(HeadServiceProvider::class);
        $this->app->register(PanelServiceProvider::class);

        foreach (self::FEATURE_PROVIDERS as $feature => $provider) {
            if (! config("accelerator.features.{$feature}", false)) {
                continue;
            }

            $this->app->register($provider);
        }
    }

    private function configureUnpublishedShieldDefaults(): void
    {
        if (is_file(config_path('filament-shield.php'))) {
            return;
        }

        config()->set([
            'filament-shield.super_admin.define_via_gate' => true,
            'filament-shield.policies.merge' => true,
            'filament-shield.policies.methods' => ['viewAny', 'view', 'create', 'update', 'delete', 'deleteAny'],
            'filament-shield.policies.single_parameter_methods' => ['viewAny', 'create', 'deleteAny'],
            'filament-shield.localization.enabled' => true,
            'filament-shield.resources.manage' => ShieldPermissions::builtIn(),
            'filament-shield.discovery.discover_all_resources' => true,
            'filament-shield.discovery.discover_all_widgets' => true,
            'filament-shield.discovery.discover_all_pages' => true,
        ]);
    }
}

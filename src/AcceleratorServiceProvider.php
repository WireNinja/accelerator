<?php

namespace WireNinja\Accelerator;

use Illuminate\Support\ServiceProvider;
use Override;
use WireNinja\Accelerator\Providers\CoreServiceProvider;
use WireNinja\Accelerator\Providers\FilamentServiceProvider;
use WireNinja\Accelerator\Providers\InsiderServiceProvider;
use WireNinja\Accelerator\Providers\OAuthServiceProvider;
use WireNinja\Accelerator\Providers\PanelServiceProvider;
use WireNinja\Accelerator\Providers\PwaServiceProvider;
use WireNinja\Accelerator\Providers\TelemetryServiceProvider;
use WireNinja\Accelerator\Providers\TicketingServiceProvider;

class AcceleratorServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<ServiceProvider>>
     */
    private const FEATURE_PROVIDERS = [
        'filament' => FilamentServiceProvider::class,
        'panels' => PanelServiceProvider::class,
        'oauth' => OAuthServiceProvider::class,
        'insider' => InsiderServiceProvider::class,
        'pwa' => PwaServiceProvider::class,
        'telemetry' => TelemetryServiceProvider::class,
        'ticketing' => TicketingServiceProvider::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accelerator.php', 'accelerator');

        $this->app->register(CoreServiceProvider::class);

        foreach (self::FEATURE_PROVIDERS as $feature => $provider) {
            if (! config("accelerator.features.{$feature}", false)) {
                continue;
            }

            if ($feature === 'panels' && ! config('accelerator.features.filament', false)) {
                continue;
            }

            $this->app->register($provider);
        }
    }
}

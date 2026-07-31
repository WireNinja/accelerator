<?php

namespace WireNinja\Accelerator;

use Illuminate\Support\ServiceProvider;
use Override;
use WireNinja\Accelerator\Providers\CoreServiceProvider;
use WireNinja\Accelerator\Providers\FilamentServiceProvider;
use WireNinja\Accelerator\Providers\HorizonServiceProvider;
use WireNinja\Accelerator\Providers\OAuthServiceProvider;
use WireNinja\Accelerator\Providers\PanelServiceProvider;
use WireNinja\Accelerator\Providers\PwaServiceProvider;

class AcceleratorServiceProvider extends ServiceProvider
{
    /**
     * @var array<string, class-string<ServiceProvider>>
     */
    private const FEATURE_PROVIDERS = [
        'oauth' => OAuthServiceProvider::class,
        'pwa' => PwaServiceProvider::class,
        'horizon' => HorizonServiceProvider::class,
    ];

    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/accelerator.php', 'accelerator');

        $this->app->register(CoreServiceProvider::class);
        $this->app->register(FilamentServiceProvider::class);
        $this->app->register(PanelServiceProvider::class);

        foreach (self::FEATURE_PROVIDERS as $feature => $provider) {
            if (! config("accelerator.features.{$feature}", false)) {
                continue;
            }

            $this->app->register($provider);
        }
    }
}

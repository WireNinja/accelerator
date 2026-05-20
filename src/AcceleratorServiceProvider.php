<?php

namespace WireNinja\Accelerator;

use Filament\Support\Assets\Css;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use Override;
use WireNinja\Accelerator\Concerns\InteractsWithApplication;
use WireNinja\Accelerator\Console\Agent\AuditCommand;
use WireNinja\Accelerator\Console\Agent\ModelContextCommand;
use WireNinja\Accelerator\Console\Agent\ResourceContextCommand;
use WireNinja\Accelerator\Console\EnvCommand;
use WireNinja\Accelerator\Console\Filament\VerifyResourceCommand;
use WireNinja\Accelerator\Console\Generator\ModelOutlineCommand;
use WireNinja\Accelerator\Console\InstallCommand;
use WireNinja\Accelerator\Console\ModelAuditCommand;
use WireNinja\Accelerator\Console\ModelDocCommand;
use WireNinja\Accelerator\Console\NotifyOverdueTicketsCommand;
use WireNinja\Accelerator\Console\Shield\SafeRegenerateCommand;
use WireNinja\Accelerator\Console\Vps\BackupStatusCommand;
use WireNinja\Accelerator\Livewire\Synthesizers\BigDecimalSynth;
use WireNinja\Accelerator\Telemetry\TelemetryDatabase;
use WireNinja\Accelerator\Telemetry\TelemetryFlusher;
use WireNinja\Accelerator\Telemetry\TelemetryManager;
use WireNinja\Accelerator\Telemetry\TelemetryNotifier;
use WireNinja\Accelerator\Telemetry\TelemetryPruneCommand;

class AcceleratorServiceProvider extends ServiceProvider
{
    use InteractsWithApplication;

    #[Override]
    public function register(): void
    {
        $this->registerTelemetry();
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'accelerator');
        $this->mergeConfigFrom(__DIR__.'/../config/accelerator.php', 'accelerator');
        $this->trustLocalProxy();

        FilamentAsset::register([
            Js::make('iconify', 'https://cdn.jsdelivr.net/npm/iconify-icon@2')->loadedOnRequest(),
            Js::make('leaflet-js', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js')->loadedOnRequest(),
            Css::make('leaflet-css', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css')->loadedOnRequest(),
        ], 'accelerator');

        Livewire::addNamespace(
            namespace: 'accelerator',
            viewPath: __DIR__.'/../resources/views/livewire',
        );

        Livewire::propertySynthesizer(BigDecimalSynth::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                AuditCommand::class,
                InstallCommand::class,
                SafeRegenerateCommand::class,
                ModelOutlineCommand::class,
                ModelDocCommand::class,
                ModelAuditCommand::class,
                ModelContextCommand::class,
                ResourceContextCommand::class,
                EnvCommand::class,
                NotifyOverdueTicketsCommand::class,
                BackupStatusCommand::class,
                VerifyResourceCommand::class,
                TelemetryPruneCommand::class,
            ]);
        }

        $this->bootCustomSessionDrivers();
        $this->bootEloquentBestPractices();
        $this->bootApplicationDefaults();
        $this->bootTelegramConfiguration();
        $this->bootShieldDestructiveCommands();
        $this->bootFilamentConfiguration();
        $this->bootTelemetry();
    }

    private function trustLocalProxy(): void
    {
        if (! config('accelerator.proxy.trust_local', true)) {
            return;
        }

        TrustProxies::at(['127.0.0.1', '::1']);
        TrustProxies::withHeaders(
            Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
    }

    /**
     * Register telemetry singletons in the container.
     */
    private function registerTelemetry(): void
    {
        $this->app->singleton(TelemetryDatabase::class);
        $this->app->singleton(TelemetryNotifier::class);

        $this->app->singleton(TelemetryFlusher::class, function ($app) {
            return new TelemetryFlusher(
                $app->make(TelemetryDatabase::class),
                $app->make(TelemetryNotifier::class),
            );
        });

        $this->app->singleton(TelemetryManager::class, function ($app) {
            return new TelemetryManager(
                $app->make(TelemetryDatabase::class),
                $app->make(TelemetryFlusher::class),
            );
        });
    }

    /**
     * Boot the telemetry subsystem if runtime supports it.
     *
     * Registers the Swoole Timer for periodic flush and resets per-request state
     * via Octane's RequestReceived event.
     */
    private function bootTelemetry(): void
    {
        if (! TelemetryManager::isSupported()) {
            return;
        }

        /** @var TelemetryManager $manager */
        $manager = $this->app->make(TelemetryManager::class);
        $manager->boot();

        // Reset per-request dedup state on each new Octane request.
        $this->app['events']->listen(
            \Laravel\Octane\Events\RequestReceived::class,
            fn () => $manager->resetRequestState(),
        );
    }
}

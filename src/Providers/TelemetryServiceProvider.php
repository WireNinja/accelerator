<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\RequestReceived;
use Override;
use WireNinja\Accelerator\Telemetry\TelemetryDatabase;
use WireNinja\Accelerator\Telemetry\TelemetryFlusher;
use WireNinja\Accelerator\Telemetry\TelemetryManager;
use WireNinja\Accelerator\Telemetry\TelemetryNotifier;
use WireNinja\Accelerator\Telemetry\TelemetryPruneCommand;

final class TelemetryServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TelemetryDatabase::class);
        $this->app->singleton(TelemetryNotifier::class);
        $this->app->singleton(
            TelemetryFlusher::class,
            static fn (Application $app): TelemetryFlusher => new TelemetryFlusher(
                $app->make(TelemetryDatabase::class),
                $app->make(TelemetryNotifier::class),
            ),
        );
        $this->app->singleton(
            TelemetryManager::class,
            static fn (Application $app): TelemetryManager => new TelemetryManager(
                $app->make(TelemetryFlusher::class),
            ),
        );
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/telemetry.php');

        if ($this->app->runningInConsole()) {
            $this->commands([TelemetryPruneCommand::class]);
        }

        if (! TelemetryManager::isSupported()) {
            return;
        }

        $manager = $this->app->make(TelemetryManager::class);
        $manager->boot();

        $this->app['events']->listen(
            RequestReceived::class,
            static function () use ($manager): void {
                $manager->resetRequestState();
            },
        );
    }
}

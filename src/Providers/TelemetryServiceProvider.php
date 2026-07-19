<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Providers;

use Illuminate\Support\ServiceProvider;
use Override;
use Swoole\Timer;
use WireNinja\Accelerator\Telemetry\TelemetryBuffer;
use WireNinja\Accelerator\Telemetry\TelemetryFlusher;
use WireNinja\Accelerator\Telemetry\TelemetryNotifier;
use WireNinja\Accelerator\Telemetry\TelemetryPruneCommand;
use WireNinja\Accelerator\Telemetry\TelemetryRecorder;
use WireNinja\Accelerator\Telemetry\TelemetryStatusCommand;
use WireNinja\Accelerator\Telemetry\TelemetryStore;

final class TelemetryServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->app->singleton(TelemetryBuffer::class);
        $this->app->singleton(TelemetryStore::class);
        $this->app->singleton(TelemetryNotifier::class);
        $this->app->singleton(TelemetryRecorder::class);
        $this->app->singleton(TelemetryFlusher::class);
    }

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/../../routes/telemetry.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                TelemetryPruneCommand::class,
                TelemetryStatusCommand::class,
            ]);
        }

        if (! TelemetryBuffer::supported()) {
            return;
        }

        $workerState = $this->app->make('Laravel\\Octane\\Swoole\\WorkerState');

        if ((int) data_get($workerState, 'workerId', -1) !== 0) {
            return;
        }

        $flusher = $this->app->make(TelemetryFlusher::class);
        $interval = max(1, (int) config('accelerator.telemetry.flush_interval', 5)) * 1000;

        // Octane's public tick API dispatches through task workers. Accelerator
        // permits zero optional task workers, so worker 0 owns this native timer.
        Timer::tick($interval, static fn () => $flusher->flush());
    }
}

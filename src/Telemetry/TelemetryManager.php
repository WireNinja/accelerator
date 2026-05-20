<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Laravel\Octane\Facades\Octane;
use Swoole\Timer;
use Throwable;

/**
 * Orchestrates the telemetry subsystem lifecycle.
 *
 * Responsibilities:
 * - Register the Swoole Table for buffering during Octane boot.
 * - Register the flush timer tick.
 * - Reset per-request state.
 * - Provide a clean API for the service provider to wire everything.
 *
 * This class is Octane-Swoole only. On FPM/CLI it silently does nothing.
 *
 * IMPORTANT: The consuming application must register the Swoole Table in config/octane.php:
 *
 *   'tables' => [
 *       ...TelemetryManager::octaneTableConfig(),
 *   ],
 *
 * This produces: 'telemetry_buffer:128' => ['payload' => 'string:65535', 'created_at' => 'int']
 */
final class TelemetryManager
{
    private bool $booted = false;

    private bool $disabled = false;

    private ?int $timerId = null;

    public function __construct(
        private readonly TelemetryDatabase $database,
        private readonly TelemetryFlusher $flusher,
    ) {}

    /**
     * Check if telemetry is enabled and the runtime supports it.
     *
     * Telemetry is Octane Swoole only. It is automatically disabled on:
     * - FPM, RoadRunner, FrankenPHP (runtime != swoole)
     * - CLI/artisan commands (would keep process alive via Timer::tick)
     * - When Swoole extension is not loaded
     * - When explicitly disabled via config
     */
    public static function isSupported(): bool
    {
        if (app()->runningInConsole()) {
            return false;
        }

        if (! config('accelerator.telemetry.enabled', true)) {
            return false;
        }

        if (config('accelerator.runtime') !== 'swoole') {
            return false;
        }

        if (! extension_loaded('swoole')) {
            return false;
        }

        return true;
    }

    /**
     * Returns the Swoole Table definition to merge into config/octane.php 'tables' array.
     *
     * Laravel Octane table format is 'name:rows' => ['column' => 'type:size'].
     *
     * Usage in config/octane.php:
     *   'tables' => [
     *       ...TelemetryManager::octaneTableConfig(),
     *   ],
     *
     * @return array<string, array<string, string>>
     */
    public static function octaneTableConfig(): array
    {
        $rows = (int) env('ACCELERATOR_TELEMETRY_BUFFER_ROWS', 128);
        $bytes = (int) env('ACCELERATOR_TELEMETRY_BUFFER_BYTES', 65535);

        return [
            TelemetryRecorder::TABLE_NAME.':'.$rows => [
                'payload' => 'string:'.$bytes,
                'created_at' => 'int',
            ],
        ];
    }

    /**
     * Boot the telemetry system. Called once during Octane worker boot.
     *
     * Registers the Swoole Table and starts the flush timer.
     */
    public function boot(): void
    {
        if ($this->booted || $this->disabled) {
            return;
        }

        $this->booted = true;

        try {
            $this->registerTimer();
        } catch (Throwable $e) {
            $this->disable($e);
        }
    }

    /**
     * Reset per-request state. Called at the beginning of each Octane request.
     */
    public function resetRequestState(): void
    {
        if ($this->disabled) {
            return;
        }

        TelemetryRecorder::resetRequestState();
    }

    /**
     * Check if telemetry has been disabled (due to errors or misconfiguration).
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Manually trigger a flush (useful for testing or graceful shutdown).
     */
    public function flush(): void
    {
        if ($this->disabled) {
            return;
        }

        $this->flusher->flush();
    }

    /**
     * Register the periodic flush timer using Swoole's native timer.
     *
     * Swoole Timer::tick is coroutine-safe and runs in the worker process.
     * It fires even when no requests are being processed, ensuring buffered
     * exceptions are persisted within the configured interval.
     */
    private function registerTimer(): void
    {
        $intervalMs = ((int) config('accelerator.telemetry.flush_interval', 5)) * 1000;

        $this->timerId = Timer::tick($intervalMs, function (): void {
            try {
                $this->flusher->flush();
            } catch (Throwable $e) {
                // Timer callback must never throw — it would crash the worker.
                rescue(fn () => logger()->warning(
                    '[Telemetry] Flush timer error.',
                    ['error' => $e->getMessage()]
                ));
            }
        });
    }

    /**
     * Disable telemetry for this process lifecycle.
     */
    private function disable(Throwable $e): void
    {
        $this->disabled = true;

        if ($this->timerId !== null) {
            Timer::clear($this->timerId);
            $this->timerId = null;
        }

        rescue(fn () => logger()->warning(
            '[Telemetry] Disabled for this process lifecycle.',
            ['error' => $e->getMessage()]
        ));
    }
}

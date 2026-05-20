<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Http\Request;
use Laravel\Octane\Facades\Octane;
use Throwable;

/**
 * Captures exception data and writes it to the Swoole Table buffer.
 *
 * This class is called from the exception handler during request processing.
 * Writing to the Swoole Table is a memory-only operation — zero disk I/O,
 * zero latency impact on the request.
 */
final class TelemetryRecorder
{
    public const TABLE_NAME = 'telemetry_buffer';

    /**
     * Static counter for generating unique row keys within a process.
     * Reset is not needed — Swoole Table keys are overwritten on flush.
     */
    private static int $sequence = 0;

    /**
     * Track fingerprints already captured in the current request to avoid duplicates.
     *
     * @var array<string, true>
     */
    private static array $capturedInRequest = [];

    /**
     * Capture an exception and buffer it in the Swoole Table.
     *
     * Returns true if buffered successfully, false if dropped (table full, disabled, filtered).
     */
    public static function capture(Throwable $exception, ?Request $request = null): bool
    {
        if (! self::shouldCapture($exception, $request)) {
            return false;
        }

        $fingerprint = ExceptionFingerprint::generate($exception);

        // Deduplicate within the same request lifecycle.
        if (isset(self::$capturedInRequest[$fingerprint])) {
            return false;
        }

        $payload = self::buildPayload($exception, $fingerprint, $request);

        try {
            $table = Octane::table(self::TABLE_NAME);

            // Check if table is full (drop newest on overflow).
            if ($table->count() >= (int) config('accelerator.telemetry.buffer_rows', 128)) {
                return false;
            }

            $key = 'exc_'.self::$sequence++;

            $table->set($key, [
                'payload' => json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                'created_at' => time(),
            ]);

            self::$capturedInRequest[$fingerprint] = true;

            return true;
        } catch (Throwable) {
            // Swoole Table not available or misconfigured — silently fail.
            return false;
        }
    }

    /**
     * Reset per-request dedup state. Called at the start of each request by TelemetryManager.
     */
    public static function resetRequestState(): void
    {
        self::$capturedInRequest = [];
    }

    /**
     * Determine if this exception should be captured based on config rules.
     */
    private static function shouldCapture(Throwable $exception, ?Request $request): bool
    {
        // Sample rate check (1-100).
        $sampleRate = (int) config('accelerator.telemetry.sample_rate', 100);
        if ($sampleRate < 100 && mt_rand(1, 100) > $sampleRate) {
            return false;
        }

        // Guest capture check.
        if (! config('accelerator.telemetry.capture_guests', false)) {
            if ($request !== null && $request->user() === null) {
                return false;
            }
            // If no request context (e.g. CLI), always capture.
            if ($request === null) {
                return true;
            }
        }

        return true;
    }

    /**
     * Build the full payload array for persistence.
     *
     * @return array<string, mixed>
     */
    private static function buildPayload(Throwable $exception, string $fingerprint, ?Request $request): array
    {
        $meta = ExceptionFingerprint::extract($exception);

        $payload = [
            'fingerprint' => $fingerprint,
            'class' => $meta['class'],
            'file' => $meta['file'],
            'line' => $meta['line'],
            'message' => mb_substr($exception->getMessage(), 0, 2000),
            'stack_trace' => $exception->getTraceAsString(),
            'user_id' => null,
            'url' => null,
            'method' => null,
            'ip' => null,
            'request_headers' => null,
            'request_payload' => null,
            'duration_ms' => null,
            'memory_usage_bytes' => memory_get_peak_usage(true),
            'created_at' => now()->toIso8601String(),
        ];

        if ($request !== null) {
            $payload['user_id'] = $request->user()?->getAuthIdentifier();
            $payload['url'] = $request->fullUrl();
            $payload['method'] = $request->method();
            $payload['ip'] = $request->ip();

            // Request duration from start.
            $startTime = $request->server('REQUEST_TIME_FLOAT');
            if ($startTime) {
                $payload['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            }

            // Headers (filtered).
            if (config('accelerator.telemetry.capture_headers', true)) {
                $payload['request_headers'] = SensitiveDataFilter::filterHeaders(
                    $request->headers->all()
                );
            }

            // Payload (filtered, optional).
            if (config('accelerator.telemetry.capture_payload', false)) {
                $payload['request_payload'] = SensitiveDataFilter::filterParams(
                    $request->all()
                );
            }
        }

        return $payload;
    }
}

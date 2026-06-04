<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Laravel\Octane\Facades\Octane;
use SplFileObject;
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
     * @var list<array{type: string, name: string, start_ms: float, duration_ms: float, connection: string, sql: string}>
     */
    private static array $timelineEvents = [];

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
        $encodedPayload = self::encodePayload($payload);

        try {
            $table = Octane::table(self::TABLE_NAME);

            // Check if table is full (drop newest on overflow).
            if ($table->count() >= (int) config('accelerator.telemetry.buffer_rows', 128)) {
                return false;
            }

            $key = 'exc_'.self::$sequence++;

            $table->set($key, [
                'payload' => $encodedPayload,
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
        self::$timelineEvents = [];
    }

    public static function recordQuery(QueryExecuted $query): void
    {
        $maxEvents = max(0, (int) config('accelerator.telemetry.timeline_max_events', 50));

        if ($maxEvents === 0 || count(self::$timelineEvents) >= $maxEvents) {
            return;
        }

        $request = self::currentRequest();

        if ($request === null) {
            return;
        }

        $endMs = self::durationSinceRequestStart($request);
        $durationMs = round((float) $query->time, 2);
        $startMs = max(0, round($endMs - $durationMs, 2));
        $maxSqlLength = max(100, (int) config('accelerator.telemetry.timeline_sql_max_length', 1000));

        self::$timelineEvents[] = [
            'type' => 'db.query',
            'name' => 'Database query',
            'start_ms' => $startMs,
            'duration_ms' => $durationMs,
            'connection' => $query->connectionName,
            'sql' => mb_substr($query->sql, 0, $maxSqlLength),
        ];
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
        $source = self::sourceContext($exception);
        $timelineEvents = self::$timelineEvents;
        $dbEvents = array_filter($timelineEvents, fn (array $event): bool => $event['type'] === 'db.query');
        $slowestDbEvent = array_reduce(
            $dbEvents,
            fn (?array $carry, array $event): array => $carry === null || $event['duration_ms'] > $carry['duration_ms'] ? $event : $carry,
            null,
        );

        $payload = [
            'fingerprint' => $fingerprint,
            'class' => $meta['class'],
            'file' => $meta['file'],
            'line' => $meta['line'],
            'message' => mb_substr($exception->getMessage(), 0, 2000),
            'stack_trace' => $exception->getTraceAsString(),
            'user_id' => null,
            'user_name' => null,
            'user_username' => null,
            'user_email' => null,
            'url' => null,
            'method' => null,
            'ip' => null,
            'request_headers' => null,
            'request_payload' => null,
            'duration_ms' => null,
            'memory_usage_bytes' => memory_get_peak_usage(true),
            'source_file' => $source['file'],
            'source_line' => $source['line'],
            'source_class' => $source['class'],
            'source_function' => $source['function'],
            'source_snippet' => $source['snippet'],
            'timeline_events' => $timelineEvents,
            'db_query_count' => count($dbEvents),
            'db_duration_ms' => round(array_sum(array_column($dbEvents, 'duration_ms')), 2),
            'slowest_query_ms' => $slowestDbEvent['duration_ms'] ?? null,
            'created_at' => now()->toIso8601String(),
        ];

        if ($request !== null) {
            $user = $request->user();

            $payload['user_id'] = $user?->getAuthIdentifier();
            $payload['user_name'] = self::stringOrNull(data_get($user, 'name'));
            $payload['user_username'] = self::stringOrNull(data_get($user, 'username'));
            $payload['user_email'] = self::stringOrNull(data_get($user, 'email'));
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private static function encodePayload(array $payload): string
    {
        $maxBytes = max(1024, (int) config('accelerator.telemetry.buffer_bytes', 65535));
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        $payload['request_payload'] = null;
        $payload['request_headers'] = null;
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        $payload['stack_trace'] = mb_substr((string) $payload['stack_trace'], 0, 20000);
        $payload['timeline_events'] = array_slice($payload['timeline_events'] ?? [], 0, 20);
        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (is_string($encoded) && strlen($encoded) <= $maxBytes) {
            return $encoded;
        }

        $payload['stack_trace'] = mb_substr((string) $payload['stack_trace'], 0, 8000);
        $payload['timeline_events'] = [];
        $payload['source_snippet'] = [];

        $encoded = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return is_string($encoded) ? $encoded : '{}';
    }

    private static function currentRequest(): ?Request
    {
        try {
            $request = request();

            return request();
        } catch (Throwable) {
            return null;
        }
    }

    private static function durationSinceRequestStart(Request $request): float
    {
        $startTime = $request->server('REQUEST_TIME_FLOAT');

        if (! $startTime) {
            return 0.0;
        }

        return round((microtime(true) - (float) $startTime) * 1000, 2);
    }

    private static function stringOrNull(mixed $value, int $limit = 255): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return mb_substr((string) $value, 0, $limit);
    }

    /**
     * @return array{file: string|null, line: int|null, class: string|null, function: string|null, snippet: array<int, array{line: int, code: string, highlight: bool}>}
     */
    private static function sourceContext(Throwable $exception): array
    {
        $frame = self::applicationFrame($exception);

        return [
            'file' => $frame['file'],
            'line' => $frame['line'],
            'class' => $frame['class'],
            'function' => $frame['function'],
            'snippet' => self::sourceSnippet($frame['file'], $frame['line']),
        ];
    }

    /**
     * @return array{file: string|null, line: int|null, class: string|null, function: string|null}
     */
    private static function applicationFrame(Throwable $exception): array
    {
        $file = $exception->getFile();
        $line = $exception->getLine();

        if (self::isApplicationFile($file)) {
            return [
                'file' => $file,
                'line' => $line,
                'class' => null,
                'function' => null,
            ];
        }

        foreach ($exception->getTrace() as $frame) {
            $file = $frame['file'] ?? null;

            if (! is_string($file) || ! self::isApplicationFile($file)) {
                continue;
            }

            return [
                'file' => $file,
                'line' => isset($frame['line']) ? (int) $frame['line'] : null,
                'class' => isset($frame['class']) ? (string) $frame['class'] : null,
                'function' => (string) $frame['function'],
            ];
        }

        return [
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'class' => null,
            'function' => null,
        ];
    }

    private static function isApplicationFile(string $file): bool
    {
        $file = str_replace('\\', '/', $file);
        $basePath = str_replace('\\', '/', base_path()).'/';

        if (! str_starts_with($file, $basePath)) {
            return false;
        }

        $relative = substr($file, strlen($basePath));

        foreach (['vendor/', 'storage/framework/', 'bootstrap/cache/'] as $excludedPrefix) {
            if (str_starts_with($relative, $excludedPrefix)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, array{line: int, code: string, highlight: bool}>
     */
    private static function sourceSnippet(?string $file, ?int $line): array
    {
        if ($file === null || $line === null || ! is_file($file) || ! is_readable($file)) {
            return [];
        }

        $radius = max(1, (int) config('accelerator.telemetry.source_radius', 5));
        $start = max(1, $line - $radius);
        $end = $line + $radius;
        $snippet = [];
        $source = new SplFileObject($file, 'r');

        for ($currentLine = $start; $currentLine <= $end; $currentLine++) {
            $source->seek($currentLine - 1);

            if ($source->eof()) {
                break;
            }

            $snippet[] = [
                'line' => $currentLine,
                'code' => rtrim((string) $source->current()),
                'highlight' => $currentLine === $line,
            ];
        }

        return $snippet;
    }
}

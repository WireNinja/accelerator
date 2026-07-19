<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use JsonException;
use Laravel\Octane\Facades\Octane;
use Throwable;

final class TelemetryBuffer
{
    public const BUFFER_TABLE = 'accelerator_telemetry_buffer';

    public const HEALTH_TABLE = 'accelerator_telemetry_health';

    private const HEALTH_KEY = 'runtime';

    private const MINIMUM_PAYLOAD_BYTES = 4096;

    /**
     * @return array<string, array<string, string>>
     */
    public static function octaneTableConfig(int $rows = 128, int $bytes = 65535): array
    {
        return [
            self::BUFFER_TABLE.':'.max(1, $rows) => [
                'payload' => 'string:'.max(self::MINIMUM_PAYLOAD_BYTES, $bytes),
                'captured_at' => 'int',
            ],
            self::HEALTH_TABLE.':1' => [
                'captured' => 'int',
                'dropped' => 'int',
                'malformed' => 'int',
                'persisted' => 'int',
                'flush_failures' => 'int',
                'notification_failures' => 'int',
                'last_flush_at' => 'int',
                'last_error' => 'string:512',
            ],
        ];
    }

    public static function supported(): bool
    {
        return (bool) config('accelerator.features.telemetry', false)
            && is_swoole_runtime();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function push(array $payload): bool
    {
        try {
            $table = Octane::table(self::BUFFER_TABLE);

            if ($table->count() >= max(1, (int) config('accelerator.telemetry.buffer_rows', 128))) {
                $this->increment('dropped');

                return false;
            }

            $bufferId = bin2hex(random_bytes(16));
            $payload['buffer_id'] = $bufferId;
            $encoded = $this->encode($payload);

            if ($encoded === null) {
                $this->increment('dropped');

                return false;
            }

            if (! $table->set($bufferId, ['payload' => $encoded, 'captured_at' => time()])) {
                $this->increment('dropped');

                return false;
            }

            $this->increment('captured');

            return true;
        } catch (JsonException) {
            $this->increment('malformed');

            return false;
        } catch (Throwable) {
            return false;
        }
    }

    public function hasRows(): bool
    {
        try {
            return Octane::table(self::BUFFER_TABLE)->count() > 0;
        } catch (Throwable) {
            return false;
        }
    }

    public function count(): int
    {
        try {
            return Octane::table(self::BUFFER_TABLE)->count();
        } catch (Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array{key: string, payload: string, captured_at: int}>
     */
    public function snapshot(): array
    {
        $rows = [];

        foreach (Octane::table(self::BUFFER_TABLE) as $key => $row) {
            $rows[] = [
                'key' => (string) $key,
                'payload' => (string) ($row['payload'] ?? ''),
                'captured_at' => (int) ($row['captured_at'] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * Delete only rows that are byte-for-byte identical to the committed snapshot.
     *
     * @param  list<array{key: string, payload: string, captured_at: int}>  $rows
     */
    public function acknowledge(array $rows): int
    {
        $table = Octane::table(self::BUFFER_TABLE);
        $deleted = 0;

        foreach ($rows as $row) {
            $current = $table->get($row['key']);

            if (! is_array($current) || ($current['payload'] ?? null) !== $row['payload']) {
                continue;
            }

            if ($table->del($row['key'])) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return array{available: bool, buffered: int, captured: int, dropped: int, malformed: int, persisted: int, flush_failures: int, notification_failures: int, last_flush_at: int, last_error: string}
     */
    public function health(): array
    {
        try {
            $health = Octane::table(self::HEALTH_TABLE)->get(self::HEALTH_KEY);
            $buffered = Octane::table(self::BUFFER_TABLE)->count();
            $available = true;
        } catch (Throwable) {
            $health = false;
            $buffered = 0;
            $available = false;
        }

        return [
            'available' => $available,
            'buffered' => $buffered,
            'captured' => (int) ($health['captured'] ?? 0),
            'dropped' => (int) ($health['dropped'] ?? 0),
            'malformed' => (int) ($health['malformed'] ?? 0),
            'persisted' => (int) ($health['persisted'] ?? 0),
            'flush_failures' => (int) ($health['flush_failures'] ?? 0),
            'notification_failures' => (int) ($health['notification_failures'] ?? 0),
            'last_flush_at' => (int) ($health['last_flush_at'] ?? 0),
            'last_error' => (string) ($health['last_error'] ?? ''),
        ];
    }

    public function recordFlush(int $persisted, int $malformed): void
    {
        $this->increment('persisted', $persisted);
        $this->increment('malformed', $malformed);
        $this->setHealth([
            'last_flush_at' => time(),
            'last_error' => '',
        ]);
    }

    public function recordFlushFailure(Throwable $exception): void
    {
        $this->increment('flush_failures');
        $this->setHealth(['last_error' => SensitiveDataFilter::text($exception->getMessage(), 500)]);
    }

    public function recordNotificationFailure(Throwable $exception): void
    {
        $this->increment('notification_failures');
        $this->setHealth(['last_error' => SensitiveDataFilter::text($exception->getMessage(), 500)]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload): ?string
    {
        $maximumBytes = max(self::MINIMUM_PAYLOAD_BYTES, (int) config('accelerator.telemetry.buffer_bytes', 65535));

        foreach ([null, 16000, 4000] as $stackLimit) {
            if ($stackLimit !== null) {
                $payload['stack_trace'] = mb_substr((string) ($payload['stack_trace'] ?? ''), 0, $stackLimit);

                if ($stackLimit === 4000) {
                    $payload['context'] = null;
                }
            }

            $encoded = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (strlen($encoded) <= $maximumBytes) {
                return $encoded;
            }
        }

        return null;
    }

    private function increment(string $column, int $amount = 1): void
    {
        if ($amount <= 0) {
            return;
        }

        try {
            Octane::table(self::HEALTH_TABLE)->incr(self::HEALTH_KEY, $column, $amount);
        } catch (Throwable) {
            // Request-path telemetry must never replace the original exception.
        }
    }

    /**
     * @param  array<string, int|string>  $values
     */
    private function setHealth(array $values): void
    {
        try {
            Octane::table(self::HEALTH_TABLE)->set(self::HEALTH_KEY, $values);
        } catch (Throwable) {
            // Runtime health is best-effort when the Swoole table is unavailable.
        }
    }
}

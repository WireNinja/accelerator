<?php

namespace WireNinja\Accelerator\Support;

use Laravel\Octane\Exceptions\ValueTooLargeForColumnException;
use Laravel\Octane\Facades\Octane;
use RuntimeException;
use SessionHandlerInterface;
use Swoole\Table;
use Throwable;

/**
 * Session handler based on a dedicated Swoole table.
 *
 * Purpose: minimize session read/write latency on the Octane Swoole runtime by
 * storing session payloads directly in shared memory.
 *
 * Important consequences:
 * - Data is lost on Octane restart / reload / deploy / crash.
 * - Cannot be used on FPM, `php artisan serve`, RoadRunner, or CLI.
 * - Payload size is limited by `SESSION_OCTANE_TABLE_BYTES`.
 *
 * This driver is only suitable for sessions that are allowed to be volatile.
 * If sessions must survive deploys, use a persistent backend like Redis.
 */
final readonly class OctaneTableSessionHandler implements SessionHandlerInterface
{
    public function __construct(
        private int $minutes,
        private string $tableName,
    ) {}

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $sessionId): string
    {
        $table = $this->table();
        $record = $table->get($sessionId);

        if ($record === false || ! isset($record['payload'], $record['last_activity'])) {
            return '';
        }

        if ($this->isExpired((int) $record['last_activity'])) {
            $table->del($sessionId);

            return '';
        }

        return (string) $record['payload'];
    }

    public function write(string $sessionId, string $data): bool
    {
        try {
            // Laravel handles payload serialization; this handler only stores the
            // raw string along with a last activity timestamp.
            return $this->table()->set($sessionId, [
                'payload' => $data,
                'last_activity' => time(),
            ]);
        } catch (ValueTooLargeForColumnException $valueTooLargeForColumnException) {
            throw new RuntimeException(sprintf(
                'Session payload [%s] is too large for Octane table [%s]. Increase SESSION_OCTANE_TABLE_BYTES to store this payload.',
                $sessionId,
                $this->tableName,
            ), $valueTooLargeForColumnException->getCode(), previous: $valueTooLargeForColumnException);
        }
    }

    public function destroy(string $sessionId): bool
    {
        $table = $this->table();

        if ($table->get($sessionId) === false) {
            return true;
        }

        return $table->del($sessionId);
    }

    public function gc(int $lifetime): int
    {
        $table = $this->table();
        $deletedSessions = 0;
        $expiredBefore = time() - $lifetime;

        foreach ($table as $sessionId => $record) {
            if ((int) ($record['last_activity'] ?? 0) > $expiredBefore) {
                continue;
            }

            if ($table->del((string) $sessionId)) {
                $deletedSessions++;
            }
        }

        return $deletedSessions;
    }

    private function isExpired(int $lastActivity): bool
    {
        return $lastActivity < (time() - ($this->minutes * 60));
    }

    private function table(): Table
    {
        try {
            return Octane::table($this->tableName);
        } catch (Throwable $throwable) {
            throw new RuntimeException(sprintf(
                'Session driver [octane-table] requires Octane Swoole and a table [%s] registered in config/octane.php.',
                $this->tableName,
            ), $throwable->getCode(), previous: $throwable);
        }
    }
}

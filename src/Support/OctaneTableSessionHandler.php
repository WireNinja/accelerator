<?php

namespace WireNinja\Accelerator\Support;

use Laravel\Octane\Exceptions\ValueTooLargeForColumnException;
use Laravel\Octane\Facades\Octane;
use RuntimeException;
use SessionHandlerInterface;
use Swoole\Table;
use Throwable;

/**
 * Session handler berbasis dedicated Swoole table.
 *
 * Tujuannya adalah meminimalkan latency baca / tulis session pada runtime
 * Octane Swoole dengan menyimpan payload session langsung di shared memory.
 *
 * Konsekuensi penting:
 * - Data hilang saat Octane restart / reload / deploy / crash.
 * - Tidak bisa dipakai pada FPM, `php artisan serve`, RoadRunner, atau CLI.
 * - Ukuran payload dibatasi oleh `SESSION_OCTANE_TABLE_BYTES`.
 *
 * Jadi driver ini cocok hanya untuk session yang boleh volatile. Jika session
 * harus bertahan saat deploy, pakai backend persisten seperti Redis.
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

    public function read(string $sessionId): string|false
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
            // Laravel sudah mengurus serialisasi payload; handler ini hanya
            // menyimpan string mentahnya beserta timestamp aktivitas terakhir.
            return $this->table()->set($sessionId, [
                'payload' => $data,
                'last_activity' => time(),
            ]);
        } catch (ValueTooLargeForColumnException $valueTooLargeForColumnException) {
            throw new RuntimeException(sprintf(
                'Session payload [%s] terlalu besar untuk Octane table [%s]. Naikkan SESSION_OCTANE_TABLE_BYTES agar payload ini bisa tersimpan.',
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

    public function gc(int $lifetime): int|false
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
                'Session driver [octane-table] membutuhkan Octane Swoole dan tabel [%s] yang sudah terdaftar di config/octane.php.',
                $this->tableName,
            ), $throwable->getCode(), previous: $throwable);
        }
    }
}

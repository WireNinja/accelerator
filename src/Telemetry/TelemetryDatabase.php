<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Manages the dedicated telemetry SQLite database connection.
 *
 * Self-healing: creates the database file and schema on first access.
 * Uses WAL mode for minimal lock contention during batch inserts.
 */
final class TelemetryDatabase
{
    private ?PDO $pdo = null;

    private bool $disabled = false;

    private bool $warningLogged = false;

    /**
     * Get the PDO connection to the telemetry SQLite database.
     * Returns null if the database is unavailable (graceful degradation).
     */
    public function connection(): ?PDO
    {
        if ($this->disabled) {
            return null;
        }

        if ($this->pdo !== null) {
            return $this->pdo;
        }

        try {
            $path = $this->databasePath();

            File::ensureDirectoryExists(dirname($path));

            $this->pdo = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 5,
            ]);

            // WAL mode for concurrent read/write without blocking.
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA synchronous=NORMAL');
            $this->pdo->exec('PRAGMA busy_timeout=5000');

            $this->ensureSchema();

            return $this->pdo;
        } catch (Throwable $e) {
            $this->disable($e);

            return null;
        }
    }

    /**
     * Get the database file path.
     */
    public function databasePath(): string
    {
        return storage_path('telemetry/telemetry.sqlite');
    }

    /**
     * Check if the database has been disabled due to an error.
     */
    public function isDisabled(): bool
    {
        return $this->disabled;
    }

    /**
     * Create the schema if tables don't exist (idempotent).
     */
    private function ensureSchema(): void
    {
        if ($this->pdo === null) {
            return;
        }

        // Check if tables already exist.
        $result = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type='table' AND name='exception_groups'"
        )->fetch();

        if ($result !== false) {
            return;
        }

        $this->pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS exception_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT NOT NULL UNIQUE,
                class TEXT NOT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                occurrence_count INTEGER NOT NULL DEFAULT 0,
                first_seen_at TEXT NOT NULL,
                last_seen_at TEXT NOT NULL,
                last_notified_at TEXT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_groups_fingerprint ON exception_groups(fingerprint);
            CREATE INDEX IF NOT EXISTS idx_groups_status ON exception_groups(status);
            CREATE INDEX IF NOT EXISTS idx_groups_last_seen ON exception_groups(last_seen_at);

            CREATE TABLE IF NOT EXISTS exception_occurrences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                stack_trace TEXT NOT NULL,
                user_id INTEGER NULL,
                url TEXT NULL,
                method TEXT NULL,
                ip TEXT NULL,
                request_headers TEXT NULL,
                request_payload TEXT NULL,
                duration_ms REAL NULL,
                memory_usage_bytes INTEGER NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (group_id) REFERENCES exception_groups(id) ON DELETE CASCADE
            );

            CREATE INDEX IF NOT EXISTS idx_occurrences_group_created ON exception_occurrences(group_id, created_at);
            CREATE INDEX IF NOT EXISTS idx_occurrences_created ON exception_occurrences(created_at);
        SQL);
    }

    /**
     * Disable the database connection and log a warning once.
     */
    private function disable(Throwable $e): void
    {
        $this->disabled = true;
        $this->pdo = null;

        if (! $this->warningLogged) {
            $this->warningLogged = true;

            rescue(fn () => logger()->warning(
                '[Telemetry] Database unavailable, telemetry disabled for this process lifecycle.',
                ['error' => $e->getMessage()]
            ));
        }
    }
}

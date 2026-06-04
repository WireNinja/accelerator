<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Support\Facades\File;
use PDO;
use Throwable;

/**
 * Manages the dedicated telemetry SQLite database connection.
 *
 * Self-healing: creates the database file and schema on first access.
 * Uses WAL mode for minimal lock contention during batch inserts.
 */
final class TelemetryDatabase
{
    public function __construct(
        private readonly TelemetryMigration $migration = new TelemetryMigration,
    ) {}

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
            $path = $this->path();

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
            $this->pdo->exec('PRAGMA foreign_keys=ON');

            $this->migration->migrate($this->pdo);

            return $this->pdo;
        } catch (Throwable $e) {
            $this->disable($e);

            return null;
        }
    }

    /**
     * Get the database file path.
     */
    public function path(): string
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
     * Disable the database connection and log a warning once.
     */
    private function disable(Throwable $e): void
    {
        $this->disabled = true;
        $this->pdo = null;

        if ($this->warningLogged) {
            return;
        }

        $this->warningLogged = true;

        rescue(fn () => logger()->warning(
            '[Telemetry] Database unavailable, telemetry disabled for this process lifecycle.',
            ['error' => $e->getMessage()]
        ));
    }
}

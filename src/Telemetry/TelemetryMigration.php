<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use PDO;
use Throwable;

final class TelemetryMigration
{
    private const SCHEMA_VERSION = 2;

    public function migrate(PDO $pdo): void
    {
        if ($this->currentVersion($pdo) === self::SCHEMA_VERSION) {
            return;
        }

        $pdo->beginTransaction();

        try {
            $this->drop($pdo);
            $this->create($pdo);
            $this->markMigrated($pdo);

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();

            throw $e;
        }
    }

    private function currentVersion(PDO $pdo): ?int
    {
        $exists = $pdo
            ->query("SELECT name FROM sqlite_master WHERE type='table' AND name='telemetry_meta'")
            ->fetch();

        if ($exists === false) {
            return null;
        }

        $version = $pdo
            ->query("SELECT value FROM telemetry_meta WHERE key = 'schema_version' LIMIT 1")
            ->fetchColumn();

        return is_numeric($version) ? (int) $version : null;
    }

    private function drop(PDO $pdo): void
    {
        $pdo->exec('DROP TABLE IF EXISTS exception_occurrences');
        $pdo->exec('DROP TABLE IF EXISTS exception_groups');
        $pdo->exec('DROP TABLE IF EXISTS telemetry_meta');
    }

    private function create(PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE telemetry_meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            CREATE TABLE exception_groups (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                fingerprint TEXT NOT NULL UNIQUE,
                class TEXT NOT NULL,
                message TEXT NULL,
                file TEXT NOT NULL,
                line INTEGER NOT NULL,
                status TEXT NOT NULL DEFAULT 'open',
                occurrence_count INTEGER NOT NULL DEFAULT 0,
                first_seen_at TEXT NOT NULL,
                last_seen_at TEXT NOT NULL,
                last_notified_at TEXT NULL
            );

            CREATE INDEX idx_groups_fingerprint ON exception_groups(fingerprint);
            CREATE INDEX idx_groups_status ON exception_groups(status);
            CREATE INDEX idx_groups_last_seen ON exception_groups(last_seen_at);

            CREATE TABLE exception_occurrences (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                group_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                stack_trace TEXT NOT NULL,
                user_id INTEGER NULL,
                user_name TEXT NULL,
                user_username TEXT NULL,
                user_email TEXT NULL,
                url TEXT NULL,
                method TEXT NULL,
                ip TEXT NULL,
                request_headers TEXT NULL,
                request_payload TEXT NULL,
                duration_ms REAL NULL,
                memory_usage_bytes INTEGER NULL,
                source_file TEXT NULL,
                source_line INTEGER NULL,
                source_class TEXT NULL,
                source_function TEXT NULL,
                source_snippet TEXT NULL,
                timeline_events TEXT NULL,
                db_query_count INTEGER NULL,
                db_duration_ms REAL NULL,
                slowest_query_ms REAL NULL,
                created_at TEXT NOT NULL,
                FOREIGN KEY (group_id) REFERENCES exception_groups(id) ON DELETE CASCADE
            );

            CREATE INDEX idx_occurrences_group_created ON exception_occurrences(group_id, created_at);
            CREATE INDEX idx_occurrences_created ON exception_occurrences(created_at);
        SQL);
    }

    private function markMigrated(PDO $pdo): void
    {
        $statement = $pdo->prepare("INSERT INTO telemetry_meta (key, value) VALUES ('schema_version', :version)");
        $statement->execute(['version' => (string) self::SCHEMA_VERSION]);
    }
}

<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Illuminate\Support\Facades\File;
use JsonException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final class TelemetryStore
{
    public const DATABASE_ID = 'accelerator-telemetry-v2';

    public const SCHEMA_VERSION = 200;

    private ?PDO $connection = null;

    public function path(): string
    {
        return storage_path('telemetry/telemetry.sqlite');
    }

    public function connection(): PDO
    {
        if ($this->connection instanceof PDO) {
            return $this->connection;
        }

        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        try {
            $connection = new PDO('sqlite:'.$path, null, null, [
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 1,
            ]);
            $connection->exec('PRAGMA journal_mode=WAL');
            $connection->exec('PRAGMA synchronous=NORMAL');
            $connection->exec('PRAGMA busy_timeout=100');
            $connection->exec('PRAGMA foreign_keys=ON');
            $this->ensureSchema($connection);
            $this->connection = $connection;

            return $connection;
        } catch (Throwable $exception) {
            $this->connection = null;

            throw $exception;
        }
    }

    public function disconnect(): void
    {
        $this->connection = null;
    }

    /**
     * Persist one immutable Swoole snapshot. A null result means another flusher owns the SQLite write lock.
     *
     * @param  list<array{key: string, payload: string, captured_at: int}>  $rows
     * @param  list<string>  $notificationChannels
     * @return array{acknowledged: list<array{key: string, payload: string, captured_at: int}>, persisted: int, malformed: int}|null
     */
    public function persist(array $rows, array $notificationChannels): ?array
    {
        if ($rows === []) {
            return ['acknowledged' => [], 'persisted' => 0, 'malformed' => 0];
        }

        $connection = $this->connection();

        try {
            $connection->exec('BEGIN IMMEDIATE');
        } catch (PDOException $exception) {
            if ($this->isLockContention($exception)) {
                return null;
            }

            throw $exception;
        }

        $acknowledged = [];
        $persisted = 0;
        $malformed = 0;

        try {
            foreach ($rows as $row) {
                try {
                    $event = $this->decodeEvent($row);
                } catch (Throwable $exception) {
                    $this->recordMalformed($connection, $row, $exception);
                    $acknowledged[] = $row;
                    $malformed++;

                    continue;
                }

                if ($this->occurrenceExists($connection, $event['buffer_id'])) {
                    $acknowledged[] = $row;

                    continue;
                }

                [$groupId, $shouldNotify, $notificationReason] = $this->upsertGroup($connection, $event);
                $this->insertOccurrence($connection, $groupId, $event);

                if ($shouldNotify && $notificationChannels !== []) {
                    $this->queueNotifications($connection, $groupId, $event, $notificationReason, $notificationChannels);
                }

                $acknowledged[] = $row;
                $persisted++;
            }

            $connection->commit();
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }

        return [
            'acknowledged' => $acknowledged,
            'persisted' => $persisted,
            'malformed' => $malformed,
        ];
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function groups(?string $status, int $page, int $perPage): array
    {
        $connection = $this->connection();
        $where = $status === null ? '' : 'WHERE groups.status = :status';
        $parameters = $status === null ? [] : ['status' => $status];
        $count = $connection->prepare("SELECT COUNT(*) FROM telemetry_groups groups {$where}");
        $count->execute($parameters);
        $offset = (max(1, $page) - 1) * $perPage;
        $query = $connection->prepare(<<<SQL
            SELECT
                groups.*,
                latest.user_id AS latest_user_id,
                latest.user_label AS latest_user_label,
                latest.method AS latest_method,
                latest.url AS latest_url,
                (
                    SELECT COUNT(DISTINCT impacted.user_id)
                    FROM telemetry_occurrences impacted
                    WHERE impacted.group_id = groups.id AND impacted.user_id IS NOT NULL
                ) AS impacted_users
            FROM telemetry_groups groups
            LEFT JOIN telemetry_occurrences latest ON latest.id = (
                SELECT occurrence.id
                FROM telemetry_occurrences occurrence
                WHERE occurrence.group_id = groups.id
                ORDER BY occurrence.occurred_at DESC, occurrence.id DESC
                LIMIT 1
            )
            {$where}
            ORDER BY groups.last_seen_at DESC, groups.id DESC
            LIMIT :limit OFFSET :offset
        SQL);

        foreach ($parameters as $key => $value) {
            $query->bindValue(':'.$key, $value);
        }

        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', $offset, PDO::PARAM_INT);
        $query->execute();

        return [
            'items' => $query->fetchAll(),
            'total' => (int) $count->fetchColumn(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function group(int $id): ?array
    {
        $statement = $this->connection()->prepare('SELECT * FROM telemetry_groups WHERE id = :id');
        $statement->execute(['id' => $id]);
        $group = $statement->fetch();

        return is_array($group) ? $group : null;
    }

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     */
    public function occurrences(int $groupId, int $page, int $perPage): array
    {
        $connection = $this->connection();
        $count = $connection->prepare('SELECT COUNT(*) FROM telemetry_occurrences WHERE group_id = :group_id');
        $count->execute(['group_id' => $groupId]);
        $query = $connection->prepare(<<<'SQL'
            SELECT *
            FROM telemetry_occurrences
            WHERE group_id = :group_id
            ORDER BY occurred_at DESC, id DESC
            LIMIT :limit OFFSET :offset
        SQL);
        $query->bindValue(':group_id', $groupId, PDO::PARAM_INT);
        $query->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $query->bindValue(':offset', (max(1, $page) - 1) * $perPage, PDO::PARAM_INT);
        $query->execute();

        return [
            'items' => array_map($this->hydrateOccurrence(...), $query->fetchAll()),
            'total' => (int) $count->fetchColumn(),
        ];
    }

    public function updateStatus(int $groupId, string $status): bool
    {
        $statement = $this->connection()->prepare('UPDATE telemetry_groups SET status = :status WHERE id = :id');
        $statement->execute(['id' => $groupId, 'status' => $status]);

        return $statement->rowCount() === 1;
    }

    /**
     * @return array{groups: int, occurrences: int, malformed: int, pending_notifications: int, failed_notifications: int, database_bytes: int, database_id: string, schema_version: int}
     */
    public function statistics(): array
    {
        $statistics = $this->connection()->query(<<<'SQL'
            SELECT
                (SELECT COUNT(*) FROM telemetry_groups) AS groups,
                (SELECT COUNT(*) FROM telemetry_occurrences) AS occurrences,
                (SELECT COUNT(*) FROM telemetry_failures) AS malformed,
                (SELECT COUNT(*) FROM telemetry_notification_outbox WHERE delivered_at IS NULL) AS pending_notifications,
                (SELECT COUNT(*) FROM telemetry_notification_outbox WHERE delivered_at IS NULL AND attempts > 0) AS failed_notifications
        SQL)->fetch();

        return [
            'groups' => (int) ($statistics['groups'] ?? 0),
            'occurrences' => (int) ($statistics['occurrences'] ?? 0),
            'malformed' => (int) ($statistics['malformed'] ?? 0),
            'pending_notifications' => (int) ($statistics['pending_notifications'] ?? 0),
            'failed_notifications' => (int) ($statistics['failed_notifications'] ?? 0),
            'database_bytes' => array_sum(array_map(
                static fn (string $path): int => is_file($path) ? (int) filesize($path) : 0,
                [$this->path(), $this->path().'-wal', $this->path().'-shm'],
            )),
            'database_id' => self::DATABASE_ID,
            'schema_version' => self::SCHEMA_VERSION,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimNotifications(int $limit = 10): array
    {
        $connection = $this->connection();
        $now = now()->utc()->toISOString();

        try {
            $connection->exec('BEGIN IMMEDIATE');
        } catch (PDOException $exception) {
            if ($this->isLockContention($exception)) {
                return [];
            }

            throw $exception;
        }

        try {
            $statement = $connection->prepare(<<<'SQL'
                SELECT id
                FROM telemetry_notification_outbox
                WHERE delivered_at IS NULL
                  AND attempts < :maximum_attempts
                  AND next_attempt_at <= :now
                  AND (locked_until IS NULL OR locked_until < :now)
                ORDER BY id
                LIMIT :limit
            SQL);
            $statement->bindValue(':maximum_attempts', max(1, (int) config('accelerator.telemetry.notification_attempts', 8)), PDO::PARAM_INT);
            $statement->bindValue(':now', $now);
            $statement->bindValue(':limit', max(1, min(50, $limit)), PDO::PARAM_INT);
            $statement->execute();
            $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));

            if ($ids === []) {
                $connection->commit();

                return [];
            }

            $token = bin2hex(random_bytes(16));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $claim = $connection->prepare("UPDATE telemetry_notification_outbox SET lock_token = ?, locked_until = ? WHERE id IN ({$placeholders})");
            $claim->execute([$token, now()->utc()->addMinute()->toISOString(), ...$ids]);
            $claimed = $connection->prepare('SELECT * FROM telemetry_notification_outbox WHERE lock_token = :token ORDER BY id');
            $claimed->execute(['token' => $token]);
            $notifications = $claimed->fetchAll();
            $connection->commit();

            return $notifications;
        } catch (Throwable $exception) {
            if ($connection->inTransaction()) {
                $connection->rollBack();
            }

            throw $exception;
        }
    }

    public function markNotificationDelivered(int $id, int $groupId, string $lockToken): void
    {
        $connection = $this->connection();
        $now = now()->utc()->toISOString();
        $connection->beginTransaction();

        try {
            $statement = $connection->prepare(<<<'SQL'
                UPDATE telemetry_notification_outbox
                SET delivered_at = :now, last_error = NULL, lock_token = NULL, locked_until = NULL
                WHERE id = :id AND lock_token = :lock_token AND delivered_at IS NULL
            SQL);
            $statement->execute(['id' => $id, 'lock_token' => $lockToken, 'now' => $now]);

            if ($statement->rowCount() === 1) {
                $group = $connection->prepare('UPDATE telemetry_groups SET last_notified_at = :now WHERE id = :id');
                $group->execute(['id' => $groupId, 'now' => $now]);
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    public function markNotificationFailed(int $id, string $lockToken, int $attempts, string $error): void
    {
        $retrySeconds = max(10, (int) config('accelerator.telemetry.notification_retry_seconds', 60));
        $delaySeconds = min(3600, $retrySeconds * (2 ** min(6, max(0, $attempts))));
        $statement = $this->connection()->prepare(<<<'SQL'
            UPDATE telemetry_notification_outbox
            SET attempts = attempts + 1,
                next_attempt_at = :next_attempt_at,
                last_error = :last_error,
                lock_token = NULL,
                locked_until = NULL
            WHERE id = :id AND lock_token = :lock_token AND delivered_at IS NULL
        SQL);
        $statement->execute([
            'id' => $id,
            'lock_token' => $lockToken,
            'next_attempt_at' => now()->utc()->addSeconds($delaySeconds)->toISOString(),
            'last_error' => SensitiveDataFilter::text($error, 500),
        ]);
    }

    /**
     * @return array{occurrences: int, groups: int, failures: int, notifications: int}
     */
    public function prune(int $days): array
    {
        $connection = $this->connection();
        $cutoff = now()->utc()->subDays(max(1, $days))->toISOString();
        $connection->beginTransaction();

        try {
            $occurrences = $this->deleteBefore($connection, 'telemetry_occurrences', 'occurred_at', $cutoff);
            $failures = $this->deleteBefore($connection, 'telemetry_failures', 'created_at', $cutoff);
            $notifications = $this->deleteDeliveredBefore($connection, $cutoff);
            $groups = $connection->exec('DELETE FROM telemetry_groups WHERE NOT EXISTS (SELECT 1 FROM telemetry_occurrences WHERE telemetry_occurrences.group_id = telemetry_groups.id)');
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $connection->exec('VACUUM');

        return [
            'occurrences' => $occurrences,
            'groups' => $groups,
            'failures' => $failures,
            'notifications' => $notifications,
        ];
    }

    private function ensureSchema(PDO $connection): void
    {
        if (! $this->hasTable($connection, 'telemetry_meta')) {
            if ($this->hasTable($connection, 'exception_groups')) {
                throw new RuntimeException("Legacy Accelerator telemetry database detected at [{$this->path()}]. Archive or remove it once before enabling telemetry v2.");
            }

            $this->createSchema($connection);

            return;
        }

        $metadata = $connection->query('SELECT key, value FROM telemetry_meta')->fetchAll(PDO::FETCH_KEY_PAIR);

        if (($metadata['database_id'] ?? null) !== self::DATABASE_ID) {
            throw new RuntimeException("Unsupported telemetry database detected at [{$this->path()}]. Archive or remove it before enabling telemetry v2.");
        }

        $version = isset($metadata['schema_version']) ? (int) $metadata['schema_version'] : 0;

        if ($version !== self::SCHEMA_VERSION) {
            throw new RuntimeException("Telemetry schema {$version} is unsupported; expected ".self::SCHEMA_VERSION.'. Run the documented incremental upgrade before starting the application.');
        }
    }

    private function createSchema(PDO $connection): void
    {
        $connection->beginTransaction();

        try {
            $connection->exec(<<<'SQL'
                CREATE TABLE telemetry_meta (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                );

                CREATE TABLE telemetry_groups (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    fingerprint TEXT NOT NULL UNIQUE,
                    exception_class TEXT NOT NULL,
                    message TEXT NOT NULL,
                    source_file TEXT NOT NULL,
                    source_line INTEGER NOT NULL,
                    route_name TEXT NULL,
                    status TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'resolved', 'muted')),
                    occurrence_count INTEGER NOT NULL DEFAULT 0,
                    first_seen_at TEXT NOT NULL,
                    last_seen_at TEXT NOT NULL,
                    last_notified_at TEXT NULL
                );

                CREATE INDEX telemetry_groups_status_last_seen ON telemetry_groups(status, last_seen_at DESC);

                CREATE TABLE telemetry_occurrences (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    buffer_id TEXT NOT NULL UNIQUE,
                    group_id INTEGER NOT NULL,
                    message TEXT NOT NULL,
                    stack_trace TEXT NOT NULL,
                    user_id TEXT NOT NULL,
                    user_label TEXT NULL,
                    url TEXT NOT NULL,
                    method TEXT NOT NULL,
                    ip TEXT NULL,
                    duration_ms REAL NULL,
                    context TEXT NULL,
                    occurred_at TEXT NOT NULL,
                    FOREIGN KEY (group_id) REFERENCES telemetry_groups(id) ON DELETE CASCADE
                );

                CREATE INDEX telemetry_occurrences_group_time ON telemetry_occurrences(group_id, occurred_at DESC);
                CREATE INDEX telemetry_occurrences_time ON telemetry_occurrences(occurred_at DESC);

                CREATE TABLE telemetry_failures (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    buffer_id TEXT NOT NULL UNIQUE,
                    payload_sha256 TEXT NOT NULL,
                    reason TEXT NOT NULL,
                    captured_at INTEGER NOT NULL,
                    created_at TEXT NOT NULL
                );

                CREATE TABLE telemetry_notification_outbox (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    group_id INTEGER NOT NULL,
                    buffer_id TEXT NOT NULL,
                    channel TEXT NOT NULL CHECK (channel IN ('discord', 'telegram')),
                    payload TEXT NOT NULL,
                    attempts INTEGER NOT NULL DEFAULT 0,
                    next_attempt_at TEXT NOT NULL,
                    lock_token TEXT NULL,
                    locked_until TEXT NULL,
                    delivered_at TEXT NULL,
                    last_error TEXT NULL,
                    created_at TEXT NOT NULL,
                    UNIQUE (buffer_id, channel),
                    FOREIGN KEY (group_id) REFERENCES telemetry_groups(id) ON DELETE CASCADE
                );

                CREATE INDEX telemetry_outbox_pending ON telemetry_notification_outbox(delivered_at, next_attempt_at, locked_until, id);
            SQL);
            $statement = $connection->prepare('INSERT INTO telemetry_meta (key, value) VALUES (:key, :value)');

            foreach (['database_id' => self::DATABASE_ID, 'schema_version' => (string) self::SCHEMA_VERSION] as $key => $value) {
                $statement->execute(['key' => $key, 'value' => $value]);
            }

            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    private function hasTable(PDO $connection, string $table): bool
    {
        $statement = $connection->prepare("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = :table");
        $statement->execute(['table' => $table]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param  array{key: string, payload: string, captured_at: int}  $row
     * @return array<string, mixed>
     */
    private function decodeEvent(array $row): array
    {
        $event = json_decode($row['payload'], true, 64, JSON_THROW_ON_ERROR);

        if (! is_array($event)) {
            throw new UnexpectedValueException('Buffered telemetry payload is not an object.');
        }

        foreach (['buffer_id', 'fingerprint', 'class', 'file', 'message', 'stack_trace', 'user_id', 'url', 'method', 'occurred_at'] as $required) {
            if (! isset($event[$required]) || ! is_string($event[$required]) || $event[$required] === '') {
                throw new UnexpectedValueException("Buffered telemetry field [{$required}] is missing or invalid.");
            }
        }

        if ($event['buffer_id'] !== $row['key']) {
            throw new UnexpectedValueException('Buffered telemetry ID does not match its Swoole table key.');
        }

        if (! isset($event['line']) || ! is_int($event['line'])) {
            throw new UnexpectedValueException('Buffered telemetry source line is invalid.');
        }

        return $event;
    }

    /**
     * @param  array{key: string, payload: string, captured_at: int}  $row
     */
    private function recordMalformed(PDO $connection, array $row, Throwable $exception): void
    {
        $statement = $connection->prepare(<<<'SQL'
            INSERT OR IGNORE INTO telemetry_failures (buffer_id, payload_sha256, reason, captured_at, created_at)
            VALUES (:buffer_id, :payload_sha256, :reason, :captured_at, :created_at)
        SQL);
        $statement->execute([
            'buffer_id' => $row['key'],
            'payload_sha256' => hash('sha256', $row['payload']),
            'reason' => SensitiveDataFilter::text($exception->getMessage(), 500),
            'captured_at' => $row['captured_at'],
            'created_at' => now()->utc()->toISOString(),
        ]);
    }

    private function occurrenceExists(PDO $connection, string $bufferId): bool
    {
        $statement = $connection->prepare('SELECT 1 FROM telemetry_occurrences WHERE buffer_id = :buffer_id');
        $statement->execute(['buffer_id' => $bufferId]);

        return $statement->fetchColumn() !== false;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{int, bool, string}
     */
    private function upsertGroup(PDO $connection, array $event): array
    {
        $select = $connection->prepare('SELECT id, status, last_notified_at FROM telemetry_groups WHERE fingerprint = :fingerprint');
        $select->execute(['fingerprint' => $event['fingerprint']]);
        $group = $select->fetch();
        $occurredAt = $event['occurred_at'];

        if (! is_array($group)) {
            $insert = $connection->prepare(<<<'SQL'
                INSERT INTO telemetry_groups (
                    fingerprint, exception_class, message, source_file, source_line, route_name,
                    status, occurrence_count, first_seen_at, last_seen_at
                ) VALUES (
                    :fingerprint, :exception_class, :message, :source_file, :source_line, :route_name,
                    'open', 1, :occurred_at, :occurred_at
                )
            SQL);
            $insert->execute([
                'fingerprint' => $event['fingerprint'],
                'exception_class' => $event['class'],
                'message' => $event['message'],
                'source_file' => $event['file'],
                'source_line' => $event['line'],
                'route_name' => $event['route_name'] ?? null,
                'occurred_at' => $occurredAt,
            ]);

            return [(int) $connection->lastInsertId(), true, 'new'];
        }

        $wasResolved = $group['status'] === 'resolved';
        $update = $connection->prepare(<<<'SQL'
            UPDATE telemetry_groups
            SET exception_class = :exception_class,
                message = :message,
                source_file = CASE WHEN :source_file LIKE 'route:%' THEN source_file ELSE :source_file END,
                source_line = CASE WHEN :source_file LIKE 'route:%' THEN source_line ELSE :source_line END,
                route_name = :route_name,
                status = CASE WHEN status = 'resolved' THEN 'open' ELSE status END,
                occurrence_count = occurrence_count + 1,
                last_seen_at = :occurred_at
            WHERE id = :id
        SQL);
        $update->execute([
            'id' => $group['id'],
            'exception_class' => $event['class'],
            'message' => $event['message'],
            'source_file' => $event['file'],
            'source_line' => $event['line'],
            'route_name' => $event['route_name'] ?? null,
            'occurred_at' => $occurredAt,
        ]);

        return [(int) $group['id'], $wasResolved, 'reopened'];
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function insertOccurrence(PDO $connection, int $groupId, array $event): void
    {
        $statement = $connection->prepare(<<<'SQL'
            INSERT INTO telemetry_occurrences (
                buffer_id, group_id, message, stack_trace, user_id, user_label, url, method,
                ip, duration_ms, context, occurred_at
            ) VALUES (
                :buffer_id, :group_id, :message, :stack_trace, :user_id, :user_label, :url, :method,
                :ip, :duration_ms, :context, :occurred_at
            )
        SQL);
        $context = isset($event['context']) && is_array($event['context'])
            ? json_encode($event['context'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : null;
        $statement->execute([
            'buffer_id' => $event['buffer_id'],
            'group_id' => $groupId,
            'message' => $event['message'],
            'stack_trace' => $event['stack_trace'],
            'user_id' => $event['user_id'],
            'user_label' => $event['user_label'] ?? null,
            'url' => $event['url'],
            'method' => $event['method'],
            'ip' => $event['ip'] ?? null,
            'duration_ms' => is_numeric($event['duration_ms'] ?? null) ? (float) $event['duration_ms'] : null,
            'context' => $context,
            'occurred_at' => $event['occurred_at'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $event
     * @param  list<string>  $channels
     */
    private function queueNotifications(PDO $connection, int $groupId, array $event, string $reason, array $channels): void
    {
        $payload = json_encode([
            'event' => $reason,
            'class' => $event['class'],
            'message' => $event['message'],
            'file' => $event['file'],
            'line' => $event['line'],
            'route_name' => $event['route_name'] ?? null,
            'occurred_at' => $event['occurred_at'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $statement = $connection->prepare(<<<'SQL'
            INSERT OR IGNORE INTO telemetry_notification_outbox (
                group_id, buffer_id, channel, payload, next_attempt_at, created_at
            ) VALUES (
                :group_id, :buffer_id, :channel, :payload, :created_at, :created_at
            )
        SQL);

        foreach ($channels as $channel) {
            $statement->execute([
                'group_id' => $groupId,
                'buffer_id' => $event['buffer_id'],
                'channel' => $channel,
                'payload' => $payload,
                'created_at' => now()->utc()->toISOString(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $occurrence
     * @return array<string, mixed>
     */
    private function hydrateOccurrence(array $occurrence): array
    {
        try {
            $context = isset($occurrence['context']) && is_string($occurrence['context'])
                ? json_decode($occurrence['context'], true, 64, JSON_THROW_ON_ERROR)
                : null;
        } catch (JsonException) {
            $context = null;
        }

        $occurrence['context'] = is_array($context) ? $context : null;

        return $occurrence;
    }

    private function deleteBefore(PDO $connection, string $table, string $column, string $cutoff): int
    {
        $statement = $connection->prepare("DELETE FROM {$table} WHERE {$column} < :cutoff");
        $statement->execute(['cutoff' => $cutoff]);

        return $statement->rowCount();
    }

    private function deleteDeliveredBefore(PDO $connection, string $cutoff): int
    {
        $statement = $connection->prepare('DELETE FROM telemetry_notification_outbox WHERE delivered_at IS NOT NULL AND delivered_at < :cutoff');
        $statement->execute(['cutoff' => $cutoff]);

        return $statement->rowCount();
    }

    private function isLockContention(PDOException $exception): bool
    {
        return str_contains(strtolower($exception->getMessage()), 'database is locked');
    }
}

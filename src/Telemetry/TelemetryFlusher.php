<?php

declare(strict_types=1);

namespace WireNinja\Accelerator\Telemetry;

use Laravel\Octane\Facades\Octane;
use PDO;
use Throwable;

/**
 * Flushes the Swoole Table buffer into the telemetry SQLite database.
 *
 * Called by a Swoole Timer tick at the configured interval. Performs batch
 * inserts for efficiency and triggers notifications for new/re-opened exceptions.
 */
final class TelemetryFlusher
{
    public function __construct(
        private readonly TelemetryDatabase $database,
        private readonly TelemetryNotifier $notifier,
    ) {}

    /**
     * Flush all buffered entries from the Swoole Table into SQLite.
     */
    public function flush(): void
    {
        if ($this->database->isDisabled()) {
            return;
        }

        try {
            $table = Octane::table(TelemetryRecorder::TABLE_NAME);
        } catch (Throwable) {
            return;
        }

        if ($table->count() === 0) {
            return;
        }

        $pdo = $this->database->connection();

        if ($pdo === null) {
            return;
        }

        try {
            $pdo->exec('BEGIN IMMEDIATE');

            // Collect rows only after the SQLite write lock is acquired.
            // Otherwise multiple Octane workers can read the same Swoole rows
            // and race each other into duplicate writes or lock failures.
            $entries = [];
            $keysToDelete = [];

            foreach ($table as $key => $row) {
                $payload = json_decode($row['payload'] ?? '{}', true);

                if (! is_array($payload) || empty($payload['fingerprint'])) {
                    $keysToDelete[] = $key;

                    continue;
                }

                $entries[] = $payload;
                $keysToDelete[] = $key;
            }

            $this->persistEntries($pdo, $entries);

            foreach ($keysToDelete as $key) {
                $table->del((string) $key);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            rescue(fn () => $pdo->rollBack());

            rescue(fn () => logger()->warning(
                '[Telemetry] Flush failed, entries retained for retry.',
                ['error' => $e->getMessage()]
            ));
        }
    }

    /**
     * Persist a batch of entries into the SQLite database.
     *
     * @param  array<int, array<string, mixed>>  $entries
     */
    private function persistEntries(PDO $pdo, array $entries): void
    {
        $now = now()->toIso8601String();

        $groupStmt = $pdo->prepare(<<<'SQL'
            INSERT INTO exception_groups (fingerprint, class, message, file, line, status, occurrence_count, first_seen_at, last_seen_at)
            VALUES (:fingerprint, :class, :message, :file, :line, 'open', 1, :now, :now)
            ON CONFLICT(fingerprint) DO UPDATE SET
                message = :message2,
                occurrence_count = occurrence_count + 1,
                last_seen_at = :now2,
                status = CASE WHEN status = 'resolved' THEN 'open' ELSE status END
        SQL);

        $occurrenceStmt = $pdo->prepare(<<<'SQL'
            INSERT INTO exception_occurrences (
                group_id, message, stack_trace, user_id, user_name, user_username, user_email,
                url, method, ip, request_headers, request_payload, duration_ms, memory_usage_bytes,
                source_file, source_line, source_class, source_function, source_snippet,
                timeline_events, db_query_count, db_duration_ms, slowest_query_ms, created_at
            )
            VALUES (
                :group_id, :message, :stack_trace, :user_id, :user_name, :user_username, :user_email,
                :url, :method, :ip, :request_headers, :request_payload, :duration_ms, :memory_usage_bytes,
                :source_file, :source_line, :source_class, :source_function, :source_snippet,
                :timeline_events, :db_query_count, :db_duration_ms, :slowest_query_ms, :created_at
            )
        SQL);

        $groupIdStmt = $pdo->prepare('SELECT id, status, last_notified_at FROM exception_groups WHERE fingerprint = :fingerprint');

        foreach ($entries as $entry) {
            $fingerprint = $entry['fingerprint'];

            // Check if this is a new group or re-open before upserting.
            $groupIdStmt->execute(['fingerprint' => $fingerprint]);
            $existingGroup = $groupIdStmt->fetch();
            $isNew = $existingGroup === false;
            $isReopen = ! $isNew && ($existingGroup['status'] ?? '') === 'resolved';

            // Upsert group.
            $groupStmt->execute([
                'fingerprint' => $fingerprint,
                'class' => $entry['class'] ?? 'Unknown',
                'message' => $entry['message'] ?? '',
                'message2' => $entry['message'] ?? '',
                'file' => $entry['file'] ?? 'unknown',
                'line' => $entry['line'] ?? 0,
                'now' => $entry['created_at'] ?? $now,
                'now2' => $entry['created_at'] ?? $now,
            ]);

            // Get group ID.
            if ($isNew) {
                $groupId = (int) $pdo->lastInsertId();
            } else {
                $groupId = (int) $existingGroup['id'];
            }

            // Insert occurrence.
            $occurrenceStmt->execute([
                'group_id' => $groupId,
                'message' => $entry['message'] ?? '',
                'stack_trace' => $entry['stack_trace'] ?? '',
                'user_id' => $entry['user_id'] ?? null,
                'user_name' => $entry['user_name'] ?? null,
                'user_username' => $entry['user_username'] ?? null,
                'user_email' => $entry['user_email'] ?? null,
                'url' => $entry['url'] ?? null,
                'method' => $entry['method'] ?? null,
                'ip' => $entry['ip'] ?? null,
                'request_headers' => isset($entry['request_headers']) ? json_encode($entry['request_headers'], JSON_UNESCAPED_SLASHES) : null,
                'request_payload' => isset($entry['request_payload']) ? json_encode($entry['request_payload'], JSON_UNESCAPED_SLASHES) : null,
                'duration_ms' => $entry['duration_ms'] ?? null,
                'memory_usage_bytes' => $entry['memory_usage_bytes'] ?? null,
                'source_file' => $entry['source_file'] ?? null,
                'source_line' => $entry['source_line'] ?? null,
                'source_class' => $entry['source_class'] ?? null,
                'source_function' => $entry['source_function'] ?? null,
                'source_snippet' => isset($entry['source_snippet']) ? json_encode($entry['source_snippet'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'timeline_events' => isset($entry['timeline_events']) ? json_encode($entry['timeline_events'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                'db_query_count' => $entry['db_query_count'] ?? null,
                'db_duration_ms' => $entry['db_duration_ms'] ?? null,
                'slowest_query_ms' => $entry['slowest_query_ms'] ?? null,
                'created_at' => $entry['created_at'] ?? $now,
            ]);

            // Notify if new or re-opened (with throttle).
            if ($isNew || $isReopen) {
                $this->maybeNotify($pdo, $groupId, $fingerprint, $entry, $isReopen);
            }
        }
    }

    /**
     * Send notification if throttle allows.
     *
     * @param  array<string, mixed>  $entry
     */
    private function maybeNotify(PDO $pdo, int $groupId, string $fingerprint, array $entry, bool $isReopen): void
    {
        $throttleMinutes = (int) config('accelerator.telemetry.throttle_minutes', 60);

        // Check last notification time.
        $stmt = $pdo->prepare('SELECT last_notified_at FROM exception_groups WHERE id = :id');
        $stmt->execute(['id' => $groupId]);
        $group = $stmt->fetch();

        if ($group && $group['last_notified_at'] !== null) {
            $lastNotified = strtotime($group['last_notified_at']);
            if ($lastNotified !== false && (time() - $lastNotified) < ($throttleMinutes * 60)) {
                return;
            }
        }

        // Update last_notified_at.
        $updateStmt = $pdo->prepare('UPDATE exception_groups SET last_notified_at = :now WHERE id = :id');
        $updateStmt->execute(['now' => now()->toIso8601String(), 'id' => $groupId]);

        // Dispatch notification.
        $this->notifier->send(
            class: $entry['class'] ?? 'Unknown',
            file: $entry['file'] ?? 'unknown',
            line: (int) ($entry['line'] ?? 0),
            message: $entry['message'] ?? '',
            isReopen: $isReopen,
        );
    }
}

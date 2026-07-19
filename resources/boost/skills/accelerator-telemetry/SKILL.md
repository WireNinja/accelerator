---
name: accelerator-telemetry
description: Configure and operate Accelerator v2 authenticated exception telemetry on Laravel Octane Swoole, including its shared-memory buffer, SQLite store, durable notification outbox, dashboard, health counters, and retention.
---

# Accelerator Telemetry v2

## Scope

Use this skill for Accelerator exception capture, Discord/Telegram delivery, the telemetry dashboard, retention, privacy settings, or telemetry health failures.

Telemetry is deliberately narrow:

- capture runs only for authenticated HTTP requests on Octane Swoole;
- the request path writes bounded data to Swoole shared memory and performs no disk I/O;
- capture still has CPU and memory cost when an exception occurs;
- SQLite persistence and notification delivery run on a native timer owned by Swoole worker 0;
- it complements Laravel logs and Nightwatch; it does not replace either.

## Installation contract

Enable the feature and keep both generated Swoole tables in `config/octane.php`:

```php
use WireNinja\Accelerator\Telemetry\TelemetryBuffer;

'tables' => [
    ...((bool) env('ACCELERATOR_FEATURE_TELEMETRY', false)
        ? TelemetryBuffer::octaneTableConfig(
            rows: (int) env('ACCELERATOR_TELEMETRY_BUFFER_ROWS', 128),
            bytes: (int) env('ACCELERATOR_TELEMETRY_BUFFER_BYTES', 65535),
        )
        : []),
],
```

Relevant environment values:

```dotenv
ACCELERATOR_FEATURE_TELEMETRY=true
ACCELERATOR_TELEMETRY_FLUSH_INTERVAL=5
ACCELERATOR_TELEMETRY_BUFFER_ROWS=128
ACCELERATOR_TELEMETRY_BUFFER_BYTES=65535
ACCELERATOR_TELEMETRY_RETENTION=90
ACCELERATOR_TELEMETRY_PRUNING=true
ACCELERATOR_TELEMETRY_SAMPLE_RATE=100

# Privacy-sensitive context remains off unless explicitly enabled.
ACCELERATOR_TELEMETRY_CAPTURE_HEADERS=false
ACCELERATOR_TELEMETRY_CAPTURE_QUERY=false
ACCELERATOR_TELEMETRY_CAPTURE_PAYLOAD=false

ACCELERATOR_TELEMETRY_NOTIFICATION_RETRY=60
ACCELERATOR_TELEMETRY_NOTIFICATION_ATTEMPTS=8
ACCELERATOR_TELEMETRY_NOTIFICATIONS_PER_FLUSH=10
# ACCELERATOR_TELEMETRY_DISCORD_WEBHOOK=
# ACCELERATOR_TELEMETRY_TELEGRAM_CHAT=
```

Telegram also needs `TELEGRAM_BOT_TOKEN` through the application services config. A channel is active only when all of its required credentials exist.

Restart Octane after changing table size, feature, or notification configuration. Swoole tables are allocated at server boot.

## Persistence guarantees

The v2 flush sequence is intentional:

1. worker 0 snapshots exact Swoole keys and payloads;
2. the store obtains SQLite's immediate write lock;
3. validate and persist each occurrence by unique `buffer_id`;
4. create notification outbox rows in the same transaction;
5. commit SQLite;
6. acknowledge only unchanged, committed Swoole rows;
7. deliver claimed outbox rows after commit.

Persistence failure leaves buffer rows available for retry. A crash after commit but before acknowledgement is safe because `buffer_id` makes replay idempotent. Notification failures never roll back occurrences; they remain visible and retryable in the outbox.

## Schema upgrades

The database lives at `storage/telemetry/telemetry.sqlite`, which maps to shared storage in the Accelerator release layout.

Accelerator never drops an unknown telemetry schema automatically. For the one-time v1 → v2 migration:

1. stop Octane;
2. move the v1 SQLite file and any `-wal` / `-shm` companions into `.accelerator_v1/telemetry/`;
3. start Octane and let v2 create schema `200`;
4. keep the archive until acceptance is complete.

Future v2 schema changes must be incremental. Never restore destructive “drop on version mismatch” behavior.

## Privacy and grouping

- Guest and CLI exceptions are never captured.
- Headers, query values, and request payloads default off.
- Enabled context is recursively bounded and redacts configured sensitive keys.
- Messages and stack traces redact common token/key patterns and Bearer credentials.
- Stored file paths are application-relative when possible.
- Fingerprints use SHA-256 over exception class, a stable application frame, and route identity.
- A resolved group reopens when it recurs. Muted groups stay muted.

Do not add a global query listener or source-file read to exception capture. Source context is read only when a Super Admin opens the dashboard.

## Operations

Dashboard: `/insider/telemetry`, guarded by `web`, `auth`, and `role:super_admin`.

It exposes captured, persisted, dropped, malformed, flush-failure, notification-failure, buffer-depth, pending-outbox, database-size, and schema counters.

```bash
php artisan telemetry:status
php artisan telemetry:status --json
php artisan telemetry:prune
php artisan telemetry:prune --days=30
```

`telemetry:status` reports storage, current buffer health, configured channels, and configuration errors without exposing notification credentials. Pruning removes expired occurrences, empty groups, rejected payload records, and delivered outbox rows, then compacts SQLite. The fresh installer schedules it daily with overlap protection.

## Failure triage

- No captures: confirm Swoole runtime, feature flag, both table definitions, authenticated request, sample rate, and an Octane restart.
- Drops rising: increase buffer rows/bytes or fix a persistence outage; allocation costs RAM and requires restart.
- Flush failures: inspect shared-storage permissions, disk space, SQLite schema version, and the dashboard's latest runtime error.
- Notification failures: inspect pending outbox rows and channel credentials/network access. Do not delete persisted exceptions.
- Schema mismatch: archive the old database explicitly. Do not bypass the guard or auto-drop it.

Keep telemetry's performance claims precise: no request-path disk I/O, not zero latency.

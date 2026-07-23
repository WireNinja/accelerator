---
name: accelerator-telemetry
description: Configure, diagnose, or change Accelerator v2 authenticated exception telemetry on Octane Swoole, including shared-memory capture, SQLite schema 200, durable outbox notifications, privacy, dashboard, status, and retention.
---

# Accelerator Telemetry v2

## Fixed scope

- Authenticated HTTP requests on Octane Swoole only.
- Request path writes bounded data to Swoole memory, not disk.
- Worker 0 timer persists to SQLite and delivers notifications.
- Telemetry complements Laravel logs/Nightwatch; it does not replace them.

Keep both configured Swoole tables and restart Octane after changing activation or table sizes.

## Durability invariant

1. Snapshot exact buffer rows.
2. Acquire SQLite write lock.
3. Persist occurrences idempotently by `buffer_id`.
4. Create notification outbox rows in the same transaction.
5. Commit.
6. Acknowledge only unchanged committed buffer rows.
7. Lease/deliver outbox rows after commit.

Never acknowledge before commit. Notification failure must not remove an occurrence.

## Schema and privacy

- Database: shared `storage/telemetry/telemetry.sqlite`.
- Required schema: `200`.
- Stop Octane and archive v1/unknown SQLite plus WAL/SHM; never auto-drop it.
- Headers, query, and payload remain off unless explicitly enabled.
- Bound/redact enabled context, messages, traces, and credential patterns.
- Do not add global query listeners or source-file reads to capture.

## Notifications

Discord needs its webhook. Telegram needs chat ID plus the application Telegram bot token. Every config key must have one documented source; never print credentials.

## Operations

```bash
php artisan telemetry:status --json
php artisan telemetry:prune
php artisan telemetry:prune --days=30
```

The dashboard is Super Admin-only. Status reports buffer/store/outbox/schema/config health without secrets. Prune only expired/delivered data and compact deliberately.

Triage missing captures by checking runtime, feature flag, Swoole tables, authentication, sample rate, and worker restart. Triage flush failures through permissions/disk/schema. Keep the claim precise: no request-path disk I/O, not zero CPU or latency.

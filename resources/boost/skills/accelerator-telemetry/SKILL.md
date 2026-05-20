---
name: accelerator-telemetry
description: Set up and use the built-in Accelerator telemetry subsystem for exception tracking, notification, and log reading — Octane Swoole only, zero request latency impact.
---

# Accelerator Telemetry

## When To Use

Setting up exception monitoring, configuring Discord/Telegram notifications for new exceptions, reviewing captured exceptions via the built-in dashboard, reading Laravel log files, or tuning telemetry buffer/retention settings.

## Overview

Accelerator ships a built-in, baked-in telemetry system that captures exceptions during Octane Swoole request processing. It is:

- **Zero latency**: writes to Swoole shared-memory Table, not disk.
- **Self-healing**: SQLite database auto-creates on first flush.
- **Gracefully degrading**: any failure silently disables telemetry for the worker lifecycle.
- **Octane Swoole only**: automatically disabled on FPM, CLI/artisan, RoadRunner, or FrankenPHP.

Exceptions are buffered in memory and batch-flushed to a dedicated SQLite database every 5 seconds (configurable). Notifications are sent to Discord/Telegram on first occurrence and re-open.

## Setup

### 1. Register the Swoole Table

Add the telemetry buffer table to your `config/octane.php`:

```php
use WireNinja\Accelerator\Telemetry\TelemetryManager;

'tables' => [
    ...TelemetryManager::octaneTableConfig(),
    // ...your other tables (sessions, etc.)
],
```

This produces the correct Octane format: `'telemetry_buffer:128' => ['payload' => 'string:65535', 'created_at' => 'int']`.

### 2. Environment Variables (all optional — defaults are sane)

```dotenv
# Master switch (default: true, auto-disabled on non-Swoole)
ACCELERATOR_TELEMETRY_ENABLED=true

# Buffer flush interval in seconds (default: 5)
ACCELERATOR_TELEMETRY_FLUSH_INTERVAL=5

# Swoole Table sizing (default: 128 rows, 64KB per row)
ACCELERATOR_TELEMETRY_BUFFER_ROWS=128
ACCELERATOR_TELEMETRY_BUFFER_BYTES=65535

# Retention: days to keep occurrence records (default: 90, 0 = forever)
ACCELERATOR_TELEMETRY_RETENTION=90
ACCELERATOR_TELEMETRY_PRUNING=true

# Capture rules
ACCELERATOR_TELEMETRY_CAPTURE_GUESTS=false
ACCELERATOR_TELEMETRY_SAMPLE_RATE=100

# Notifications (leave empty to disable)
ACCELERATOR_TELEMETRY_DISCORD_WEBHOOK=
ACCELERATOR_TELEMETRY_TELEGRAM_CHAT=
ACCELERATOR_TELEMETRY_THROTTLE=60
```

### 3. Notification Setup

**Discord**: Create a webhook in your Discord channel settings → Integrations → Webhooks. Paste the URL into `ACCELERATOR_TELEMETRY_DISCORD_WEBHOOK`.

**Telegram**: Use your existing Telegram bot token (from `SystemSettings` or `services.telegram-bot-api.token`). Set `ACCELERATOR_TELEMETRY_TELEGRAM_CHAT` to your chat/group ID.

Both channels fire simultaneously if both are configured.

### 4. Deploy Consideration

The telemetry SQLite lives at `storage/telemetry/telemetry.sqlite`. Since `storage/` is symlinked to `{root}/shared/storage` in the Envoy release layout, the database persists across deploys automatically.

## How It Works

```
Request → Exception thrown → TelemetryRecorder::capture()
    ↓
Swoole Table buffer (memory-only, zero I/O)
    ↓ (every 5 seconds via Swoole Timer)
TelemetryFlusher::flush()
    ↓
Batch INSERT into storage/telemetry/telemetry.sqlite
    ↓
New/re-opened? → Discord/Telegram notification (throttled)
```

## Exception Grouping

Exceptions are grouped by **fingerprint**: `md5(class + file + line)`. Same exception from same location = same group, regardless of message variations.

Group statuses:
- `open` — actively occurring
- `resolved` — manually marked resolved by operator
- `muted` — manually silenced (no notifications)

When a resolved group re-appears, it automatically re-opens and triggers a notification.

## Dashboard

The telemetry dashboard is available at `/insider/telemetry` (Super Admin only). It provides:

- **Exception Groups**: list of all captured exception types with occurrence count, status, first/last seen.
- **Occurrence Detail**: full stack trace, request context, headers, timing for each individual occurrence.
- **Log Reader**: paginated view of `laravel.log` entries.

## Commands

```bash
# Prune old telemetry records (scheduled daily at 04:00)
php artisan telemetry:prune

# Override retention days
php artisan telemetry:prune --days=30
```

## Configuration Reference

All keys live under `config('accelerator.telemetry.*')`:

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `enabled` | bool | `true` | Master switch |
| `flush_interval` | int | `5` | Seconds between buffer flushes |
| `buffer_rows` | int | `128` | Max buffered exceptions (Swoole Table rows) |
| `buffer_bytes` | int | `65535` | Max payload size per row (bytes) |
| `retention_days` | int | `90` | Days to keep occurrence records |
| `pruning_enabled` | bool | `true` | Whether scheduled pruning runs |
| `capture_guests` | bool | `false` | Capture exceptions from unauthenticated requests |
| `sample_rate` | int | `100` | Percentage of exceptions to capture (1-100) |
| `notify.discord_webhook` | string | `null` | Discord webhook URL |
| `notify.telegram_chat_id` | string | `null` | Telegram chat ID for notifications |
| `throttle_minutes` | int | `60` | Min minutes between notifications per fingerprint |
| `capture_headers` | bool | `true` | Include request headers in occurrence |
| `capture_payload` | bool | `false` | Include request body (privacy-sensitive) |
| `sensitive_params` | array | `[password, token, ...]` | Parameters to redact |
| `sensitive_headers` | array | `[Authorization, Cookie, ...]` | Headers to redact |

## Graceful Degradation

- Swoole Table not registered → telemetry silently disabled, warning logged once.
- SQLite write failure (disk full, permissions) → telemetry disabled for worker lifecycle.
- Buffer overflow (128 rows full) → newest exceptions dropped (catastrophic flood protection).
- Notification failure → swallowed by `rescue()`, never crashes the app.
- CLI/artisan process → `isSupported()` returns false immediately (prevents Timer::tick from keeping CLI alive).

## Rules

- Do not store the telemetry SQLite inside a per-release directory. It must live in shared storage.
- Do not use telemetry on FPM — it will silently not activate.
- Do not rely on telemetry as a replacement for structured logging. It captures exceptions only.
- Pruning is scheduled but can also be triggered manually for immediate cleanup.
- The dashboard is Super Admin only. Do not expose to regular users.

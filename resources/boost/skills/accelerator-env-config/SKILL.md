---
name: accelerator-env-config
description: Inspect or change Accelerator local env, optional features, deployment schema 3, and ignored stage runtime files without mutating a server.
---

# Accelerator environment and configuration

| File | Ownership |
|---|---|
| `.env` | Local runtime and local secrets. |
| `.env.example` | Public key contract; no credentials. |
| `.accelerator/deploy.json` | Committed schema-3 topology; no secrets. |
| `.accelerator/environments/{stage}.env` | Ignored canonical stage runtime and secrets. |

Use `php artisan accelerator:configure application|features|deployment|environment`. Use `accelerator:env:validate`, `accelerator:env:diff`, and `accelerator:feature:list --json` for read-only checks. `accelerator:env:push` is a separate remote mutation.

Schema 3 stores stable `deployment_key`, repository/branch, package manager, PHP-FPM defaults, and stage host/domain/root. It has no `port_base`, HTTP-runtime choice, Supervisor program, queue-worker count, local Reverb switch, or telemetry daemon switch.

Optional features are OAuth, PWA, Telegram, realtime, Scout, and observability. Realtime means centralized Reverb client. Observability means direct OpenTelemetry export to OpenObserve. Queue connection is always `database`; Redis remains optional for cache and sessions.

Never print or commit credentials. Preserve backup, Telegram, database, OAuth, Reverb, and OTLP secrets during reconfiguration. `--rotate-app-key` is destructive and requires explicit intent. Realtime uses the same centralized Reverb app ID, key, and secret in local, staging, and production; the central server's ignored `.env` is the source of truth. OpenObserve headers must be distinct per app stage.

Dual stages run identical code but isolate database, uploads, APP_KEY, OTLP credential/identity, Redis/cache prefixes, session cookie, and backup namespace. Reverb credentials are deliberately shared because the centralized server exposes one application. Single-stage disables the data indicator; dual-stage labels local/test/live data.

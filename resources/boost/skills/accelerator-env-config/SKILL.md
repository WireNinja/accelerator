---
name: accelerator-env-config
description: Inspect or change Accelerator local application env and optional feature configuration without mutating a server. Use Easyploy for deployment topology and stage environments.
---

# Accelerator environment and configuration

| File | Ownership |
|---|---|
| `.env` | Local runtime and local secrets. |
| `.env.example` | Public key contract; no credentials. |
| `.accelerator/install-state.json` | Ignored resumable installer receipt only. |
| `.accelerator/reverb-apps.json` | Ignored local centralized-Reverb registration payload. |
| `.easyploy/manifest.json` | Committed non-secret deployment topology. |
| `.easyploy/environments/{stage}.env` | Ignored stage runtime and secrets. |

Use `php artisan accelerator:configure application|features`, `accelerator:env`, `accelerator:feature:list --json`, and `accelerator:doctor --json` for local application config.

Use `easyploy init|config|env` for topology and stage environments. Never make Accelerator recreate a second deployment control plane.

Optional features are OAuth, PWA, Telegram, realtime, Scout, and observability. Realtime is a centralized Reverb client. Observability is direct OTLP export to OpenObserve. Queue connection is always database; Redis is optional for cache/sessions.

Never print or commit credentials. `--rotate-reverb-app` is destructive. Dual stages run identical code and isolate all mutable runtime data and credentials.

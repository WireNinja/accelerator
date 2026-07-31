---
name: accelerator-env-config
description: Inspect or change Accelerator local env, optional features, committed deployment topology, and ignored stage runtime files without mutating a server.
---

# Accelerator environment and configuration

| File | Purpose |
|---|---|
| `.env` | Local Laravel runtime/features. |
| `.env.example` | Public key contract. |
| `.accelerator/deploy.json` | Committed non-secret single-VPS topology. |
| `.accelerator/environments/{stage}.env` | Ignored Laravel stage runtime/secrets. |
| `.accelerator/install-state.json` | Install resume receipt only. |

Use `php artisan accelerator:configure application|features|deployment|environment`. Configuration is local-only: validate full drafts, redact secrets, confirm writes, refresh caches, and never SSH/migrate/deploy/restart.

Application code calls `config()`, never `env()` outside config files. Keep Telegram/OAuth/database/Nightwatch/VAPID secrets in env files, never settings or deployment JSON. Root `/` remains userland-owned.

Optional features are OAuth, PWA, Telegram, Horizon, Reverb, Scout, and Nightwatch. Filament, settings, RBAC, audit, and core UI are always active. Use `accelerator:env` and `config:show accelerator` for read-only inspection.

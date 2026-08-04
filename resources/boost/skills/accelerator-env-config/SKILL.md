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

Use `php artisan accelerator:configure application|features|deployment|environment`. Non-interactive writes require deterministic options, `--force`, and may use `--json`. Use `accelerator:feature:list --json` to compare declared features with loaded runtime integrations. Configuration is local-only: validate full drafts, redact secrets, confirm writes, refresh caches, and never SSH/migrate/deploy/restart.

Canonical stage secrets live locally. Use `accelerator:env:edit`, `accelerator:env:validate`, and the read-only `accelerator:env:diff`. `accelerator:env:push` is a separate authorized deployment operation: it uploads atomically, clears cached configuration, restarts only the exact stage group, and health-checks. Never edit remote `.env` during normal operation.

Deployment schema 2 stores one stable `deployment_key` and one explicit `port_base`. Supervisor names and stage service ports are derived, not independently mutable. Replace ACME email only with explicit `--ssl-email`. Stage-scoped `--rotate-app-key` and `--rotate-reverb-credentials` are destructive credential rotations intended for first deployment or an explicit incident response; never rotate an established live stage implicitly.

Application code calls `config()`, never `env()` outside config files. Keep Telegram/OAuth/database/Nightwatch/VAPID secrets in env files, never settings or deployment JSON. Root `/` remains userland-owned.

Optional features are OAuth, PWA, Telegram, Horizon, Reverb, Scout, and Nightwatch. Filament, settings, RBAC, audit, and core UI are always active. Use `accelerator:env` and `config:show accelerator` for read-only inspection.

Google OAuth enrollment policy is stage configuration, not a mutable database setting. `existing_only` is the safe default; `allowed_domains` requires an explicit `ACCELERATOR_OAUTH_ALLOWED_DOMAINS` allowlist and `ACCELERATOR_OAUTH_DEFAULT_ROLE`. Feature configuration must preserve either valid mode. Telegram enables the notification transport and generic profile Chat ID/test UI only; business notifications and recipient preferences stay in userland.

Dual-stage topology must set `ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED=true` locally and in both stage env files. Default labels are `LOCAL DATA`, `TEST DATA`, and `LIVE DATA`, with `info`, `warning`, and `danger` colors respectively. Single-production topology must keep the indicator disabled. `accelerator:configure deployment` owns the enable/disable state; each env may customize only its label and color. Composer updates never mutate env files or activate the indicator.

Dual stages are independent, identical instances: keep code, dependencies, features, and application behavior equal while isolating domain and all mutable data/runtime state. When instances share one Redis server, `accelerator:configure environment` owns distinct `{deployment_key}_{stage}` values for `REDIS_PREFIX`, `CACHE_PREFIX`, `HORIZON_NAME`, `HORIZON_PREFIX`, and `SESSION_COOKIE`; never make these equal across staging and production. Reverb credentials must also be distinct.

Legacy `.accelerator/deploy.env` may be converted once with `accelerator:configure deployment --migrate-legacy --force --json --no-interaction`. Only non-secret topology is retained, roots are normalized to `/var/www/{domain}`, and the legacy file is deleted only after a valid `deploy.json` is written.

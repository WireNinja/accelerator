---
name: accelerator-ops-observability
description: Inspect Accelerator runtime state, logs, backup status, OPcache, Horizon, Nightwatch, Reverb, Supervisor, and Nginx — including JSON output for AI agents — without touching unrelated services.
---

# Accelerator Ops Observability

## When To Use

Read-only debugging of deployment health, runtime services, OPcache state, logs, backup status, Horizon, Nightwatch, Reverb, Octane, Supervisor, or Nginx in an Accelerator project.

Use `accelerator-deployment` when the task requires deploying, bootstrapping, rollback, pruning, restarting services, or changing Nginx/Supervisor/systemd state.
Use `accelerator-env-config` when the task is about `.env`, `.env.envoy`, config keys, or safe env redaction.

## Primary Checks

Use Envoy for deploy/service state:

```bash
vendor/bin/envoy run status --stage=test
vendor/bin/envoy run releases --stage=test
vendor/bin/envoy run logs --stage=test --service=octane
```

Use `--stage=prod` only after confirming production is the intended target.
Do not run `restart`, `deploy`, `rollback`, `bootstrap`, or `prune` from this skill unless the user explicitly asks for a mutating operation; switch to `accelerator-deployment` for that workflow.

## Backup Status

```bash
php artisan vps:backup-status                # human-readable table
php artisan vps:backup-status --json         # JSON pretty
php artisan vps:backup-status --json --compact  # JSON single-line for piping
```

JSON shape:

```json
{
  "status": "OK",
  "disk": "local",
  "app_name": "WSS - Local",
  "physical_path": "...",
  "summary": {
    "total_files": 14,
    "total_size_bytes": 1234567890,
    "total_size_human": "1.15 GB",
    "last_backup_at": "2026-05-19T03:00:12+00:00",
    "last_file": "2026-05-19-03-00-12.zip"
  },
  "files": [...]
}
```

The command uses `$disk->files()` (top-level) by design — Spatie zips are flat in `{APP_NAME}/`. No deep traversal.

## Pre-Deploy Backup

Pre-deploy DB backups land in a separate Spatie profile:

```bash
ls storage/app/private/{APP_NAME}-predeploy/
```

Filename prefix: `predeploy-`. Aggressive retention (2 days). Notifications disabled. To restore manually:

```bash
unzip -p storage/app/private/{APP_NAME}-predeploy/2026-05-19-...zip db-dumps/database.sql | mysql -u {user} -p{pwd} {db}
```

Adjust extraction path / db client per environment. There is no automated `db-restore` task by design.

## Comprehensive Audit

```bash
php artisan agent:audit                      # 7-section table
php artisan agent:audit --json --compact     # JSON for AI / CI
```

Sections: PHP Core, Resource Limits, Extensions, Performance (OPCache + JIT), Build Tools, Accelerator Integration, Environment.

When `App\Models\User` or `App\Providers\Filament\AdminPanelProvider` is missing, the audit emits an actionable warning telling the operator to run `accelerator:install --component=filament-core`.

## Server Checks (SSH)

Scope every command to the configured root, domain, supervisor group:

```bash
readlink -f {root}/current
sudo nginx -t
sudo supervisorctl status {group}:*
ss -ltnp
curl -I -L https://{domain}
# HTTP_RUNTIME=octane only:
curl -s -o /dev/null -w "%{http_code}" -H "Host: {domain}" http://127.0.0.1:{octane_port}/up
# HTTP_RUNTIME=fpm:
curl -s -o /dev/null -w "%{http_code}" -L https://{domain}/up
```

Use the health command matching `OPS_DEPLOY_{STAGE}_HTTP_RUNTIME`. Envoy checks Octane directly, while FPM is checked through the Nginx vhost.

## Reaudit After Cleanup

```bash
sudo supervisorctl status | grep '{group}'
sudo grep -RIl '{domain}\|{root}\|{group}' /etc/nginx /etc/supervisor
find {root} -maxdepth 2 -mindepth 1 -print
find {root}/releases -mindepth 1 -maxdepth 1 -type d | wc -l
```

Expected clean state:

- one current release unless rollback history is intentionally kept
- `{root}/current` points inside `{root}/releases`
- `{root}/archive` contains only deliberate snapshots, not old app trees
- no `{root}/html`
- no deploy runner folders
- no stale env backup files in `{root}/shared`
- no generic or cross-project Supervisor names
- Nginx references only `{root}/current/public`

## OPcache

- Per-release `opcache_invalidate()` during deploy.
- Do NOT use global `opcache_reset()` as the default — OPcache may be shared with unrelated PHP apps.
- Healthy deploy state has `restart_pending=false` after the deploy settles.
- If `validate_timestamps=false`, changed PHP files require deploy invalidation + service restart.

## Nightwatch

- Opt-in. `NIGHTWATCH_ENABLED=true` renders the managed `nightwatch:agent` process; use explicit per-stage port configuration in `.env.envoy`.
- Runtime `.env.staging` / `.env.production` must set `NIGHTWATCH_ENABLED=true` and `NIGHTWATCH_INGEST_URI` to the matching listener.
- Do not assume the Nightwatch port is free — check listeners before enabling.

## Static Asset 404s

For Livewire, Filament, or dynamic package JavaScript 404s behind Nginx, check whether a static asset location is intercepting `.js` requests before Laravel/Octane can handle route-backed assets.

## Out Of Scope

Do not use this skill for:

- Shield regeneration; use the Filament/security workflow that is changing permissions.
- Model relationship/cast/schema review; use `accelerator-model-outline`.
- Filament resource gates; use `accelerator-filament`.
- Deployment mutation; use `accelerator-deployment`.

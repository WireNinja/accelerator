---
name: accelerator-ops-observability
description: Diagnose Accelerator runtime, releases, logs, backups, OPcache, Octane, Horizon, Reverb, Nightwatch, Nginx, and Supervisor without changing server state. Use for read-only local or VPS health inspection; switch to accelerator-deployment for mutations.
---

# Accelerator Ops Observability

## Scope first

Confirm the configured stage, domain, deploy root, runtime, ports, and Supervisor group. Inspect only that scope. Do not restart, deploy, rollback, prune, unlock, or rewrite infrastructure from this skill.

Verify available tasks, then prefer package summaries:

```bash
vendor/bin/envoy tasks
vendor/bin/envoy run status --stage=production
vendor/bin/envoy run releases --stage=production
vendor/bin/envoy run logs --stage=production --service=octane
php artisan vps:backup-status --json --compact
php artisan telemetry:status --json
```

Use the enabled stage; never default to production without confirmation.

## Evidence order

1. configured local deploy/runtime state;
2. active `current` symlink and matching immutable env;
3. scoped Supervisor programs and expected listeners;
4. Nginx syntax/vhost and `/up` through Nginx;
5. matching application/service logs;
6. backup and telemetry summaries.

Do not dump secrets, full environment files, unrelated process lists, or unrelated vhosts.

## Runtime notes

- FPM releases use distinct realpaths; do not globally reset OPcache.
- Octane deploys restart only their scoped process group.
- Static package JavaScript 404s may be caused by an Nginx static location intercepting a Laravel route.
- Nightwatch/Reverb/Octane ports must match stage config and be distinct.
- Backup restoration is manual and database-specific; status is not restore authorization.

## Insider direction

Treat browser diagnostics as bounded read-only summaries. Do not expose full session dumps or synthetic session mutation. Prefer compact JSON/CLI for deep operational detail.

Use `accelerator-deployment` before any server mutation and `accelerator-env-config` before changing local configuration.

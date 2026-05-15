---
name: accelerator-ops-observability
description: Inspect Accelerator operational state, logs, backup status, OPcache, Horizon, Nightwatch, Reverb, and Supervisor without touching unrelated services.
---

# Accelerator Ops Observability

## When To Use

Use this skill when debugging deployment health, runtime services, OPcache state, logs, backup status, Horizon, Nightwatch, Reverb, Octane, or server process state in an Accelerator project.

## Commands

Use stage-scoped commands first:

```bash
php artisan ops:status --stage=test
php artisan ops:logs octane --stage=test
php artisan ops:logs horizon --stage=test
php artisan ops:logs reverb --stage=test
php artisan ops:logs scheduler --stage=test
php artisan ops:restart all --stage=test
php artisan ops:backup-status --stage=test
```

Use `--stage=prod` only after confirming production is the intended target.

## Server Checks

For SSH diagnostics:

```bash
readlink -f {root}/current
sudo nginx -t
sudo supervisorctl status {group}:*
ss -ltnp
curl -I -L https://{domain}
```

Keep all commands scoped to the configured root, domain, and Supervisor group.

## OPcache

- Prefer per-release `opcache_invalidate()` during deploy.
- Do not use global `opcache_reset()` as the default because it can affect unrelated PHP applications sharing the same OPcache process.
- Healthy deploy state should have `restart_pending=false` after the deploy settles.
- If `validate_timestamps=false`, changed PHP files require deploy invalidation and service restart to be reflected.

## Nightwatch

- Nightwatch is opt-in.
- Use explicit host and port.
- Do not assume the Nightwatch port is free. Check listeners before enabling it.

## Static Asset 404s

For Livewire, Filament, or dynamic package JavaScript 404s behind Nginx, check whether a static asset location is intercepting `.js` requests before Laravel/Octane can handle route-backed assets.

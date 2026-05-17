---
name: accelerator-ops-observability
description: Inspect Accelerator runtime state, logs, backup status, OPcache, Horizon, Nightwatch, Reverb, Supervisor, and Nginx without touching unrelated services.
---

# Accelerator Ops Observability

## When To Use

Use this skill when debugging deployment health, runtime services, OPcache state, logs, backup status, Horizon, Nightwatch, Reverb, Octane, Supervisor, or Nginx in an Accelerator project.

## Primary Checks

Use Envoy for deploy/service state:

```bash
vendor/bin/envoy run status --stage=test
vendor/bin/envoy run logs --stage=test --service=octane
vendor/bin/envoy run restart --stage=test --service=all
```

Use `--stage=prod` only after confirming production is the intended target.

## Backup Status

Use:

```bash
php artisan vps:backup-status
```

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

---
name: accelerator-ops-observability
description: Diagnose Accelerator releases, logs, backups, Nginx, PHP-FPM, native cron, database queue drains, centralized Reverb client connectivity, and OpenTelemetry export without changing server state.
---

# Accelerator operations observability

Confirm configured deployment key, stage, domain, root, and SSH host first. Inspect only that project.

```bash
php artisan accelerator:doctor --json
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:backup:list --stage=production --json
php artisan accelerator:backup:status --stage=production --json
php artisan accelerator:backup:verify --stage=production --backup=<exact-id> --json
```

Evidence order: committed topology; active `current` symlink; Nginx vhost and `/up`; exact PHP-FPM service; exact `/etc/cron.d/acc-{deployment_key}-{stage}` file; scheduler/Laravel logs; queue backlog; backup status; disk/release state. Inspect only key presence for secrets.

Centralized Reverb and OpenObserve are separate host services. Diagnose client configuration locally first; touching either central service requires separate explicit authority. Use `accelerator-observability` for OTLP-specific checks.

Inspect backup age, size, disk reachability, checksum result, archive components, encryption state, local free space, and the last lifecycle result. Read `../accelerator-deployment/references/backup-restore.md` for the artifact contract.

This skill is read-only. Do not create, clean, deploy, restart, rollback, unlock, restore, or rewrite config. Switch to `accelerator-deployment` only after explicit mutation authority.

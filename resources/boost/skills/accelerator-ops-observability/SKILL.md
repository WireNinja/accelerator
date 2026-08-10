---
name: accelerator-ops-observability
description: Diagnose Accelerator releases, runtime, logs, backups, Octane, Horizon, Reverb, NightOwl, Nginx, and Supervisor without changing server state.
---

# Accelerator operations observability

Confirm configured stage/domain/root/runtime/ports/group first. Inspect only that project.

```bash
php artisan accelerator:doctor --json
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:backup:list --stage=production --json
php artisan accelerator:backup:status --stage=production --json
php artisan accelerator:backup:verify --stage=production --backup=<exact-id> --json
```

Evidence order: committed topology; active `current` symlink; scoped service state/listeners; Nginx vhost and `/up`; matching logs; backup status; disk/release state. Never dump env contents, credentials, unrelated processes, or unrelated vhosts.

Inspect backup age, size, disk reachability, checksum result, archive components, encryption state, local free space, and the last lifecycle result. Read `../accelerator-deployment/references/backup-restore.md` for the artifact contract.

This skill is read-only. Do not create, clean, deploy, restart, rollback, unlock, restore, or rewrite config. Switch to `accelerator-deployment` only after explicit mutation authority.

---
name: accelerator-ops-observability
description: Diagnose Accelerator releases, runtime, logs, backups, Octane, Horizon, Reverb, Nightwatch, Nginx, and Supervisor without changing server state.
---

# Accelerator operations observability

Confirm configured stage/domain/root/runtime/ports/group first. Inspect only that project.

```bash
php artisan accelerator:doctor --json
php artisan accelerator:deploy:status --stage=production --json
php artisan vps:backup-status --json --compact
```

Evidence order: committed topology; active `current` symlink; scoped service state/listeners; Nginx vhost and `/up`; matching logs; backup status; disk/release state. Never dump env contents, credentials, unrelated processes, or unrelated vhosts.

This skill is read-only. Do not deploy, restart, rollback, unlock, prune, restore, or rewrite config. Switch to `accelerator-deployment` only after explicit mutation authority.

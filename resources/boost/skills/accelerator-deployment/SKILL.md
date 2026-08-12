---
name: accelerator-deployment
description: Operate Accelerator v2 Deployer-backed Artisan init, deploy, promotion, rollback, relocation, Nginx, PHP-FPM, native cron, backups, and stage env pushes. Use only for authorized server mutation.
---

# Accelerator deployment

Public operations originate locally through Artisan. Never invoke Deployer or edit the remote release directly during normal operation.

```bash
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:init --stage=production --revision=<full-sha>
php artisan accelerator:deploy --stage=staging
php artisan accelerator:deploy:promote --from=staging --to=production
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:env:push --stage=production
php artisan accelerator:logs laravel --stage=production --lines=200
php artisan accelerator:logs scheduler --stage=production --lines=200
```

Before mutation, confirm `deployment_key`, stage, SSH alias, domain, exact `/var/www/{domain}` root, revision, and the matching ignored stage env. Then run preflight. Never infer production authority.

## Runtime contract

- Nginx forwards PHP to shared PHP 8.5 FPM. Accelerator owns no Octane process.
- One `/etc/cron.d/acc-{deployment_key}-{stage}` entry invokes `schedule:run` each minute with `flock`.
- Laravel's package-owned schedule drains the database queue every 10 seconds using a bounded `queue:work --stop-when-empty`; no Horizon or queue Supervisor exists.
- The application is a client of centralized Reverb. No local Reverb server or proxy location exists.
- Laravel exports OTLP directly to centralized OpenObserve. No Nightwatch/NightOwl/Collector daemon or telemetry database exists.
- A deployment removes only the exact old `acc-{deployment_key}-{stage}` Supervisor config/programs. Never uninstall Supervisor or touch unrelated groups.
- Deploy and env push clear caches, interrupt an old sub-minute scheduler, reload exact PHP-FPM, and perform HTTPS health checks.

Deployer still owns atomic releases, shared env/storage, migrations, symlink switching, cleanup, locks, and code rollback. Database migrations never roll back automatically. Dual-stage promotion deploys the exact successful staging revision.

Read [references/backup-restore.md](references/backup-restore.md) before restore. Backups and restores remain stage-scoped and explicit.

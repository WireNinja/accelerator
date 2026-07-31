---
name: accelerator-deployment
description: Operate Accelerator v2 Deployer-backed Artisan init, deploy, rollback, unlock, relocation, Nginx, Supervisor, backups, and stage services. Use only for authorized server mutation.
---

# Accelerator deployment

Public operations are Artisan commands; never instruct the user to invoke `vendor/bin/dep` directly.

```bash
php artisan accelerator:deploy:init --stage=production
php artisan accelerator:deploy --stage=production
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:deploy:unlock --stage=production
php artisan accelerator:deploy:relocate --stage=production --old-root=/var/www/old.example.com
```

Before mutation, read committed `.accelerator/deploy.json`, confirm stage/SSH alias/domain/root/service group, and confirm the matching ignored stage env exists. Non-interactive mutation requires explicit force. Never guess production targets.

Stable root is `/var/www/{domain}`. Deployer owns lock, releases, shared dirs/files, vendors, writable paths, symlink switching, cleanup, and rollback. Accelerator owns env upload, frozen pnpm/npm build, pre-migration DB backup, Laravel ordering, Nginx/Supervisor rendering, service restart, and HTTPS health.

Never run Composer update remotely, print env contents, touch unrelated projects, auto-rollback migrations, or delete the old root during relocation. Horizon and a plain queue worker are mutually exclusive. A code rollback changes the symlink and services only; database recovery is manual.

Use `accelerator-ops-observability` for diagnosis before mutation.

---
name: accelerator-deployment
description: Operate Accelerator v2 Deployer-backed Artisan init, deploy, rollback, unlock, relocation, Nginx, Supervisor, backups, and stage services. Use only for authorized server mutation.
---

# Accelerator deployment

Public operations are Artisan commands; never instruct the user to invoke `vendor/bin/dep` directly.

```bash
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:init --stage=production
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy --stage=production
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:deploy:unlock --stage=production
php artisan accelerator:deploy:relocate --stage=production --old-root=/var/www/old.example.com
```

Before mutation, read committed `.accelerator/deploy.json`, confirm stage/SSH alias/domain/root/service group, confirm the matching ignored stage env exists, then run `accelerator:deploy:preflight`. Non-interactive mutation requires explicit force. Never guess production targets.

Stage environment configuration must explicitly match Horizon, Reverb, and Nightwatch service flags in the committed topology. Use `accelerator:configure environment --stage=...`; never assume an installed package means its runtime provider is enabled.

Staging and production use separate `{project}_{stage}` Supervisor groups and separate Octane/Reverb/Nightwatch ports when they share an SSH host. Never merge stages into one process group: status, restart, deploy, and rollback must remain stage-scoped. Preflight rejects unmanaged roots, Nginx domain ownership conflicts, Supervisor group/program conflicts, and occupied listener ports before provision/deploy mutates the server. Accelerator ownership markers may authorize an existing stage; `--force` never authorizes taking over another project.

Stable root is `/var/www/{domain}`. Deployer owns lock, releases, shared dirs/files, vendors, writable paths, symlink switching, cleanup, and rollback. Accelerator owns env upload, frozen pnpm/npm build, pre-migration DB backup, Laravel ordering, Nginx/Supervisor rendering, service restart, and HTTPS health. A post-switch health failure may restore only the previous code symlink; it must report that database recovery remains manual.

Runtime/deploy writable paths use inherited ACLs. A release is rollback-eligible only after its services pass the retried HTTPS health check; never target an unfinished, failed, or unmarked release.

Supervisor owns long-running service restarts. Do not add Laravel's generic post-deploy `artisan reload`; it duplicates the stage-scoped restart and may not signal processes owned by the runtime user.

Certbot manages certificate material through Accelerator's ACME webroot and must not rewrite Nginx. Accelerator renders and owns the complete HTTP/HTTPS virtual host.

Deployment clones the root repository and initializes only `packages/accelerator` before Composer. Never replace that scoped command with recursive or all-submodule initialization: unrelated gitlinks are outside Accelerator deployment scope.

Never run Composer update remotely, print env contents, touch unrelated projects, auto-rollback migrations, or delete the old root during relocation. Relocation locks the project, copies into the new stable root, reprovisions Nginx/SSL/services, health-checks, and deliberately preserves the old root. Horizon and a plain queue worker are mutually exclusive. A code rollback changes the symlink and services only; database recovery is manual.

Use `accelerator-ops-observability` for diagnosis before mutation.

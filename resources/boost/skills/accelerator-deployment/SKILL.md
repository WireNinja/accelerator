---
name: accelerator-deployment
description: Operate Accelerator v2 Deployer-backed Artisan init, deploy, rollback, unlock, relocation, Nginx, Supervisor, backups, and stage services. Use only for authorized server mutation.
---

# Accelerator deployment

Public operations are Artisan commands; never instruct the user to invoke `vendor/bin/dep` directly.

```bash
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:init --stage=production --revision=<full-commit-sha>
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy --stage=production
php artisan accelerator:deploy:promote --from=staging --to=production
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:deploy:unlock --stage=production
php artisan accelerator:deploy:relocate --stage=production --old-root=/var/www/old.example.com
php artisan accelerator:service:status all --stage=production --json
php artisan accelerator:service:start octane --stage=production
php artisan accelerator:service:stop octane --stage=production
php artisan accelerator:service:restart octane --stage=production
php artisan accelerator:logs laravel --stage=production --lines=200
php artisan accelerator:backup --stage=production --only=all
php artisan accelerator:ports --host=ssh-alias --range=9000-9999 --available=20
```

Before mutation, read committed `.accelerator/deploy.json`, confirm deployment key/stage/SSH alias/domain/root/derived Supervisor group, validate the matching ignored stage env, then run `accelerator:deploy:preflight`. Non-interactive mutation requires explicit force. Never guess production targets.

Stage environment configuration must explicitly match Horizon, Reverb, and Nightwatch service flags in the committed topology. Use `accelerator:configure environment --stage=...`; never assume an installed package means its runtime provider is enabled.

Treat dual stages as independent, identical instances of one application. Code, dependencies, features, UI, and deployment behavior must match; only domain and mutable data/runtime ownership differ. Verify SQL databases, uploads, `APP_KEY`, Reverb credentials, Redis/cache/Horizon prefixes, session cookie names, logs, and processes are instance-scoped before mutation. Separate Supervisor groups alone do not prevent one instance's worker from consuming the other instance's Redis queue.

`deployment_key` is the stable machine identity. Derive Supervisor groups as `acc-{deployment_key}-{stage}`; never store arbitrary stage group names. Reserve one 20-port block per project: staging uses offsets 0-9 and production offsets 10-19; single-stage production uses offsets 0-9. Octane, Reverb, and Nightwatch use offsets 0, 1, and 2. Scan before assigning `port_base`; never auto-select ports during deployment.

Never merge stages into one process group. Status, restart, deploy, and rollback remain stage-scoped. Preflight rejects unmanaged roots, Nginx domain ownership conflicts, Supervisor group/program conflicts, and occupied listener ports before provision/deploy mutates the server. Accelerator ownership markers may authorize a legacy Accelerator-owned stage during the deterministic group rename; `--force` never authorizes taking over another project.

Stable root is `/var/www/{domain}`. Deployer owns lock, releases, shared dirs/files, vendors, writable paths, symlink switching, cleanup, and rollback. Accelerator owns env upload, frozen pnpm/npm build, pre-migration DB backup, Laravel ordering, Nginx/Supervisor rendering, service restart, and HTTPS health. A post-switch health failure may restore only the previous code symlink; it must report that database recovery remains manual.

`deploy:init` creates a missing first database before backup and migration. Pass the reviewed full commit SHA through `--revision` when release identity matters. It may create stage-owned SQLite, or a local MySQL/MariaDB/PostgreSQL database for an existing application account through passwordless sudo. It never creates database users, changes passwords, or provisions external database servers. Ordinary deploys never create databases.

Runtime/deploy writable paths use inherited ACLs. A release is rollback-eligible only after its services pass the retried HTTPS health check; never target an unfinished, failed, or unmarked release.

File backups include mutable `storage/app` by default. Never replace that with the release root: code belongs in Git, while release-tree backups leak `.env` and archive disposable vendors/build artifacts. Add another path only through `accelerator.backup.include` when the application truly owns mutable data there.

For dual stages, deploy to staging first and promote only its marked-successful exact Git revision. Never promote a branch head that differs from the revision the client reviewed.

Supervisor owns long-running service restarts. Do not add Laravel's generic post-deploy `artisan reload`; it duplicates the stage-scoped restart and may not signal processes owned by the runtime user.

Certbot manages certificate material through Accelerator's ACME webroot and must not rewrite Nginx. Accelerator renders and owns the complete HTTP/HTTPS virtual host.

Keep the generated Nginx exception for Livewire v4's hash-based `/livewire-{hash}/` routes before the static file-extension location. Those JavaScript and update endpoints are dynamic Laravel routes; do not fix a 404 by publishing vendor assets.

Deployment clones the root repository and initializes only `packages/accelerator` before Composer. Never replace that scoped command with recursive or all-submodule initialization: unrelated gitlinks are outside Accelerator deployment scope.

Never run Composer update remotely, print env contents, touch unrelated projects, auto-rollback migrations, or delete the old root during relocation. Relocation locks the project, copies into the new stable root, reprovisions Nginx/SSL/services, health-checks, and deliberately preserves the old root. Horizon and a plain queue worker are mutually exclusive. A code rollback changes the symlink and services only; database recovery is manual.

Use `accelerator-ops-observability` for diagnosis before mutation.

---
name: accelerator-deployment
description: Deploy Accelerator v2 Laravel applications with the strict Envoy init/deploy/rollback flow, rendered Nginx and Supervisor infrastructure, immutable release env files, maintenance safety, backups, and scoped server operations.
---

# Accelerator v2 Deployment

## Use This Skill For

First deployment, continuous deployment, destructive fresh-seed deployment, infrastructure rendering, SSL, rollback, runtime restart, or any change to the release layout, Nginx, Supervisor, or deployment services.

Use `accelerator-env-config` for key ownership and runtime configuration. Use `accelerator-ops-observability` for read-only diagnosis.

## Non-Negotiable Safety

- Confirm the intended stage, domain, root, SSH host, and Supervisor group before mutation.
- Scope every remote operation to that configuration. Never enumerate, restart, rewrite, or delete unrelated projects.
- Run `preflight` before the first remote mutation when adopting an existing server.
- Never commit `.env.envoy`, `.env.staging`, or `.env.production`.
- Never put `OPS_DEPLOY_*` in a Laravel runtime env.
- Never run `composer update` on the VPS. Every release installs committed locks.
- Never bypass the destructive fresh-seed confirmation phrase.
- Never clear maintenance after a failed health check. Roll back or repair first.
- Never delete `{root}/archive` as part of release pruning.

## Public Contract

```bash
# Inspect local + remote readiness without changing the server
vendor/bin/envoy run preflight --stage=staging

# First release: layout, app, Nginx, Supervisor, SSL, health
vendor/bin/envoy run init --stage=staging

# Later releases
vendor/bin/envoy run deploy --stage=staging

# Explicit destructive reset after a database backup
vendor/bin/envoy run deploy-fresh-seed --stage=staging \
  --i-understand-this-will-drop-and-reseed-database="aku mengkonfirmasi remigrate fresh seed"

# Recovery and operations
vendor/bin/envoy run rollback --stage=staging
vendor/bin/envoy run unlock --stage=staging
vendor/bin/envoy run restart --stage=staging --service=all
vendor/bin/envoy run status --stage=staging
vendor/bin/envoy run releases --stage=staging
vendor/bin/envoy run backups --stage=staging
vendor/bin/envoy run logs --stage=staging --service=octane

# Expert repair operations
vendor/bin/envoy run bootstrap --stage=staging
vendor/bin/envoy run ssl --stage=staging
vendor/bin/envoy run render --stage=staging
```

Valid stages are `staging` and `production`. A single-stage installation enables only production. A dual-stage installation defaults to staging.

Removed v1 commands have no compatibility alias: `deploy-slim`, `bootstrap-ssl`, and the `test` / `prod` stage names are invalid.

## Configuration Ownership

The application `Envoy.blade.php` contains only server aliases and imports the package bridge. All deploy behavior lives in the package.

`.env.envoy`:

- contains only `OPS_DEPLOY_*` values;
- selects repository, branch, stage, domain, root, runtime, ports, and services;
- stays local with mode `0600`;
- is parsed strictly: duplicate, legacy, unknown, malformed, unsafe, or inconsistent values fail before tasks run.

`.env.staging` and `.env.production`:

- contain Laravel runtime values only;
- stay local with mode `0600`;
- use `APP_ENV=production`, `APP_DEBUG=false`, an explicit HTTPS `APP_URL`, and a nonblank `APP_KEY`;
- use `{root}/shared/database/database.sqlite` for SQLite;
- are uploaded to a temporary path and installed as immutable per-release env files.

The initial administrator password is never stored as plaintext. The local deploy config may contain its bcrypt hash; that hash is still sensitive authentication material.

## Release Layout

```text
{root}/
├── current -> releases/{release}
├── releases/{release}/
│   ├── .env -> shared/env/{release}.env
│   ├── storage -> shared/storage
│   └── public/storage -> shared/storage/app/public
├── shared/
│   ├── .env -> env/{current-release}.env
│   ├── env/{release}.env
│   ├── storage/
│   ├── database/
│   └── acme/
└── archive/
```

Every release retains its own immutable env symlink. `current` selects code that already points to its matching env, while `shared/.env` follows the active release for operational compatibility. Release pruning deletes an env only after no retained release references it.

## `init` Flow

`init` is resumable for the same Git commit and performs:

1. local lock, env, Git, Composer, Pint, and remote-head checks;
2. remote tools, PHP version, disk, runtime, port, and DNS checks;
3. scoped release/shared/archive layout and ACL creation;
4. immutable runtime env staging;
5. rendered Nginx and Supervisor installation with backups and validation;
6. exact shallow clone of the local/remote commit;
7. shared links, locked Composer install, locked Bun install, and asset build;
8. release permissions, normal migration, initial Super Admin provisioning, storage link, and Laravel caches;
9. atomic current switch, stage service activation, and `/up` health check;
10. ACME public-path probe, Certbot, HTTPS vhost activation, local/public HTTPS checks, and release pruning.

There is no separate bootstrap-before-init ceremony. If SSL fails after HTTP health succeeds, the application remains usable over HTTP and reports `PARTIAL_READY`; fix DNS/firewall/Certbot and run `envoy ssl`.

## Continuous `deploy` Flow

Before maintenance:

1. repeat local and remote preflight;
2. reject Nginx/Supervisor drift from the v2 renderer;
3. stage an immutable env and build a complete release;
4. scan added or modified migrations for obvious destructive operations;
5. back up the active database through `backup_predeploy`.

Risky window:

1. create Laravel's shared maintenance marker;
2. Nginx returns `503` for app, static, and websocket traffic before PHP, except `/up` and ACME;
3. migrate and optimize the new release;
4. atomically switch `current`;
5. restart only the configured Supervisor group;
6. require Nginx + runtime `/up` to return `200`;
7. remove maintenance only when this deploy owns the marker and health passed.

Finally, prune old releases while preserving current, retained rollback releases, referenced env files, and the entire archive.

If build, migration, runtime, or health fails, Envoy exits non-zero. A post-maintenance failure deliberately leaves the app in maintenance for operator triage.

Mutating stories acquire `{root}/shared/.accelerator-deploy-lock`. A failed deploy keeps that lock so a second deploy cannot race the failed state. After confirming no Envoy process is active, run `envoy unlock`; it removes only the deploy lock and never clears maintenance. Then repair or roll back.

## Infrastructure Ownership And Drift

The v2 renderer owns exactly:

- `/etc/nginx/sites-available/{domain}.conf`;
- `/etc/nginx/sites-enabled/{domain}.conf` symlink;
- `/etc/supervisor/conf.d/{group}.conf` when at least one managed service is enabled.

Before replacement, existing target files are copied to `{root}/archive`. Nginx candidates must pass `nginx -t`; failed candidates restore the previous file. Supervisor candidates must pass `supervisorctl reread`; disabling all programs removes the scoped file and runs `update` so old processes stop.

Normal deploy rejects manual drift. Either move intentional changes into the renderer/config or run `bootstrap` to replace the scoped generated files. Do not edit generated files as a permanent configuration strategy.

## Runtime Rules

- `HTTP_RUNTIME=fpm`: Nginx uses the configured Unix socket. Deploy does not reload the shared FPM service; the new release realpath creates distinct OPcache keys and avoids restarting unrelated pools/projects.
- `HTTP_RUNTIME=octane`: Supervisor starts `octane:swoole` directly for Swoole so an explicit task-worker count of `0` is preserved; other supported servers use `octane:start`.
- Octane requires at least one request worker. Swoole task workers may be `0` and should increase only when application code uses task dispatch.
- Enable Horizon or the plain queue worker, never both.
- Reverb, Scheduler, and Nightwatch are independent stage flags.
- Enabled ports must be distinct and unused during fresh init.
- Supervisor names are `{group}_{service}` and operations target only `{group}:*`.
- Upload limits are 100 MB with a 110 MB PHP/Nginx request envelope.

## Rollback

Rollback selects the newest complete non-current release, validates its autoload file, prepared marker, and env symlink, switches code and the shared env alias to the same retained release before restarting the scoped runtime, and requires `/up=200` before clearing deploy-owned maintenance.

Database rollback is manual. A code rollback cannot undo a committed migration safely. Prefer backward-compatible expand/contract migrations even though the deploy scanner blocks obvious destructive operations by default.

## Fresh Seed

`deploy-fresh-seed` exists for an explicitly disposable stage. It:

- takes a database backup first;
- enters maintenance;
- installs dev dependencies temporarily so seed tooling is available;
- disables Laravel's destructive-command guard only inside the one command process;
- runs `migrate:fresh --seed --force`;
- provisions/resynchronizes the initial administrator;
- prunes dev dependencies and rebuilds caches before switching.

Do not use it as a migration shortcut for production data.

## Diagnosis

```bash
vendor/bin/envoy run status --stage=staging
vendor/bin/envoy run releases --stage=staging
vendor/bin/envoy run backups --stage=staging
vendor/bin/envoy run logs --stage=staging --service=all
vendor/bin/envoy run render --stage=staging
```

Use `logs --service=all` for `laravel.log`; service values are `octane`, `horizon`, `queue`, `reverb`, `scheduler`, and `nightwatch`.

When health fails, inspect only the configured domain/root/group. Check the rendered config, `nginx -t`, scoped Supervisor status, the active symlink/env target, and the matching application/service log. Do not widen the server scope without explicit authorization.

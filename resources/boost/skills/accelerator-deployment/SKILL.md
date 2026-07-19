---
name: accelerator-deployment
description: Deploy Laravel apps with WireNinja Accelerator Envoy release flow — first-time init, continuous deploy with maintenance window, db backup, health check, prune-releases, rollback validation, and Supervisor / Nginx safety.
---

# Accelerator Deployment

## When To Use

First deployment, continuous deployment, deployment cleanup, Envoy release folders, `.env.envoy` / `.env.staging` / `.env.production`, Nginx, Supervisor, Octane, Reverb, Horizon, Scheduler, Nightwatch, OPcache, `larahelp`, `setfacl`, db backup, maintenance mode, health checks, release prune, rollback.

Use `accelerator-env-config` first when the task is only about env/config key contracts or safe env inspection.
Use `accelerator-ops-observability` when the task is read-only diagnosis of an already deployed runtime and no deploy/restart should happen.

## Non-Negotiable Rules

- Envoy is the deployment orchestrator.
- Do not bootstrap Laravel config from Envoy.
- Envoy reads deploy config directly from project-root `.env.envoy`.
- `OPS_DEPLOY_*` keys belong only in `.env.envoy`, never in `.env`, `.env.staging`, `.env.production`, `.env.example`, or `.base-env.example`.
- Runtime env seeding comes from `.env.staging` (test stage) or `.env.production` (prod stage).
- Envoy syncs the selected runtime env seed to `{root}/shared/.env` on every deploy and archives the old shared env first.
- Use `larahelp --reoptimize` and `larahelp --setfacl` directly. Do not add fallback abstractions.
- Scope every SSH command to the configured domain, root, group, and ports.
- Never touch unrelated domains, Nginx files, Supervisor groups, `/var/www` roots, ports, or services.
- Do not serve Laravel from legacy `{root}/html/public`; Nginx must serve `{root}/current/public`.
- Envoy does NOT auto-generate Nginx vhost or Supervisor config on every deploy. Run the one-shot `bootstrap` story per VPS to write them once (operator can edit later); continuous deploy just restarts/reloads.
- Generated deployments default to PHP-FPM with no optional Supervisor services. Enable Octane, Horizon/queue worker, Reverb, Scheduler, and Nightwatch explicitly in `.env.envoy`.
- Use Horizon or the plain queue worker, never both on the same stage.
- `deploy-fresh-seed` is destructive and must only run with the exact explicit Envoy flag shown below. It temporarily installs Composer dev dependencies so seeders/factories can use `fake()`, then prunes dev packages before switching current.
- Every release build requires committed `composer.lock` and installs from that lockfile with `composer install`, never `composer update`. Deployment must use exactly the dependency versions reviewed in development; resolving newer package versions on the VPS expands supply-chain exposure.

## Stories Cheatsheet

| Story | When | What it runs |
|---|---|---|
| `bootstrap` | one-shot per VPS to write Nginx vhost + Supervisor conf | bootstrap-nginx + bootstrap-supervisor |
| `bootstrap-ssl` | one-shot per domain to obtain cert + upgrade Nginx to SSL | obtain-cert + upgrade-nginx-ssl |
| `init` | first deploy on a fresh VPS root | layout setup + tooling check + clone + build + harden + prepare-laravel + switch + opcache + restart + health-check |
| `deploy` | continuous full deploy | tooling + sync-env + clone + build + harden + clear-cache + migration-safety + db-backup + maintenance-on + prepare-laravel + switch + opcache + restart + health-check + maintenance-off + prune |
| `deploy-slim` | hot patch backend only (no JS/CSS rebuild) | same as deploy minus build-release |
| `deploy-fresh-seed` | destructive redeploy with fresh DB + seeded data | explicit phrase gate + tooling + sync-env + clone + build with Composer dev deps + backup + maintenance + `migrate:fresh --seed` + prune dev deps + switch + opcache + restart + health-check + maintenance-off + prune |
| `rollback` | switch back to previous valid release | rollback-release + opcache + restart + health-check (NO maintenance window — speed prioritised) |
| `releases` | list release history + prune target | tabular output |
| `backups` | list predeploy and scheduled backup files | list-backups |
| `status` | quick state check | readlink current + supervisor status |
| `restart` / `logs` | targeted service operation | supervisorctl / tail |

`init` SKIPS db-backup, maintenance, prune, and migration-safety by design — the first deploy has no `current` symlink, no DB rows worth backing up, and no old releases to remove.

## Minimal Project Files

```text
Envoy.blade.php
.env.envoy          # rendered from package `.base-env.envoy.example`
.env.staging
.env.production
```

`Envoy.blade.php` only defines server aliases and imports the package bridge:

```blade
@servers(['vps' => ['onidel'], 'localhost' => '127.0.0.1'])

@import('vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php')
```

The `vps` server alias must point to the same SSH host as `OPS_DEPLOY_SSH_HOST`. Envoy remote tasks run through the alias, while package bootstrap tasks use `OPS_DEPLOY_SSH_HOST` for local `ssh` / `scp` calls.

Do not copy deployment shell scripts into the project.

## Required `.env.envoy` Keys

Per stage, `OPS_DEPLOY_{STAGE}_HTTP_RUNTIME` selects `fpm` or `octane`. FPM health-checks through Nginx; Octane health-checks its direct port.

Global keys:

- `OPS_DEPLOY_DEFAULT_STAGE`, `OPS_DEPLOY_PROJECT`, `OPS_DEPLOY_SSH_HOST`
- `OPS_DEPLOY_REPO`, `OPS_DEPLOY_BRANCH`
- `OPS_DEPLOY_KEEP_RELEASES` (default 5)
- `OPS_DEPLOY_PHP_VERSION` (preferred PHP intent, e.g. `8.5` or `8.4`; if empty, PHP auto-detects)
- `OPS_DEPLOY_PHP_BIN` (advanced override; when empty, derives from `OPS_DEPLOY_PHP_VERSION` or auto-detects in order: php8.5 → php8.4 → php8.3 → php)
- `OPS_DEPLOY_PACKAGE_MANAGER_BIN` (supports `pnpm`, `bun`, or `npm`; auto-detects if empty — fallback order: pnpm → bun → npm)
- `OPS_DEPLOY_RUN_USER` (default `www-data`)
- `OPS_DEPLOY_SSL_EMAIL`

Per stage (`TEST` / `PROD`):

- `OPS_DEPLOY_{STAGE}_ENABLED`
- `OPS_DEPLOY_{STAGE}_DOMAIN`, `OPS_DEPLOY_{STAGE}_ROOT`
- `OPS_DEPLOY_{STAGE}_GROUP` (Supervisor group, stage-scoped)
- `OPS_DEPLOY_{STAGE}_HTTP_RUNTIME` (`fpm` default, or `octane`)
- `OPS_DEPLOY_{STAGE}_FPM_POOL` (FPM only; optional pool name such as `wahyudi`; derives dedicated socket/service names when set)
- `OPS_DEPLOY_{STAGE}_FPM_SOCKET` (advanced FPM socket override; when empty, derives from PHP version + pool, then falls back to selected PHP)
- `OPS_DEPLOY_{STAGE}_FPM_SERVICE` (advanced FPM systemd service override; when empty, derives from PHP version + pool and falls back to shared `phpX.Y-fpm.service`)
- `OPS_DEPLOY_{STAGE}_OCTANE_SERVER` (Octane only: `swoole`, `roadrunner`, or `frankenphp`)
- `OPS_DEPLOY_{STAGE}_OCTANE_PORT` (required for Octane)
- `OPS_DEPLOY_{STAGE}_OCTANE_WORKERS` (request workers; default `1`, must be >= `1`)
- `OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS` (Swoole task workers; default `0`, set >= `1` only when tasks are used)
- `OPS_DEPLOY_{STAGE}_HORIZON_ENABLED`
- `OPS_DEPLOY_{STAGE}_QUEUE_WORKER_ENABLED`, `_QUEUE_WORKER_CONNECTION`, `_QUEUE_WORKER_QUEUE`, `_QUEUE_WORKER_PROCESSES`
- `OPS_DEPLOY_{STAGE}_REVERB_ENABLED`, `_REVERB_PORT`
- `OPS_DEPLOY_{STAGE}_SCHEDULER_ENABLED`
- `OPS_DEPLOY_{STAGE}_NIGHTWATCH_ENABLED`, `_NIGHTWATCH_PORT`

`.env.staging` and `.env.production` carry runtime application keys, key-compatible with `.env`.

For SQLite, share the database file across releases:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/example.com/shared/database/database.sqlite
```

## Server Layout

```text
{root}/archive
{root}/current -> {root}/releases/{releaseId}
{root}/releases/{releaseId}
{root}/shared/.env
{root}/shared/storage
```

Per-release symlinks managed by Envoy:

```text
release/.env            -> {root}/shared/.env
release/storage         -> {root}/shared/storage
release/public/storage  -> {root}/shared/storage/app/public
```

Release folder format: `YYYY-MM-DD_HH-MM-SS_shortsha`.

## Service Naming

Programs MUST be group-prefixed to avoid cross-project Supervisor collisions.

```text
{group}_octane
{group}_horizon
{group}_queue_worker  (alternative to Horizon, never together)
{group}_reverb
{group}_scheduler
{group}_nightwatch
```

Examples for `wss_test`:

```text
wss_test:wss_test_octane
wss_test:wss_test_horizon
wss_test:wss_test_reverb
wss_test:wss_test_scheduler
```

Every listed program is conditional. In FPM mode there is no Octane program; PHP-FPM is managed by systemd, not Supervisor. Envoy resolves the service from `OPS_DEPLOY_PHP_VERSION` and optional `OPS_DEPLOY_{STAGE}_FPM_POOL`, then reloads that service during `restart-service`. Reverb also controls whether its websocket Nginx location exists. Nightwatch runs `nightwatch:agent` only when enabled; runtime env must set `NIGHTWATCH_ENABLED=true` and `NIGHTWATCH_INGEST_URI` to the configured port.

Octane concurrency is opt-in beyond the minimum request worker. The generated Supervisor command never uses `auto`: it starts with one request worker and, for Swoole, zero task workers. Apps that call `Octane::concurrently()` or otherwise dispatch Swoole tasks must set `OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS` to an intentional positive count and re-run `bootstrap`.

## DB Backup During Deploy

Envoy's `db-backup` task runs:

```bash
php artisan backup:run --config=backup_predeploy --only-db --disable-notifications --no-interaction --ansi
```

The `backup_predeploy` profile (shipped via Accelerator stub):

- Folder: `{APP_NAME}-predeploy/` (separate from scheduled backup pool).
- Filename prefix: `predeploy-`.
- Notifications: disabled (every deploy would otherwise spam).
- Retention: aggressive — `keep_all_backups_for_days=2`, weekly/monthly/yearly = 0, `delete_oldest_when > 1000MB`.

Restore is **manual by design**. Locate the latest zip under `storage/app/private/{APP_NAME}-predeploy/`, unzip, feed dump to native `mysql` / `psql` / `sqlite3`. There is no Envoy `db-restore` task.

## Maintenance Window

Continuous `deploy` runs `php artisan down --secret={random per deploy} --redirect=/` between sandbox build and the migrate/switch zone. Envoy prints the secret URL once:

```
Maintenance bypass URL: https://{domain}/{secret}
```

Visit ONCE in your browser to set the bypass cookie, then preview while deploy continues. After health-check passes, `php artisan up` removes maintenance.

If the deploy fails between `maintenance-on` and `maintenance-off` (e.g. health-check returns non-200), the app stays in maintenance until the operator runs `php artisan up` manually or rolls back. This is intentional — better to keep traffic blocked than to expose a broken release.

`rollback` does NOT use a maintenance window. It swaps the release, restarts only enabled Supervisor programs, then runs the runtime-specific health check. Adding a maintenance overlay slows down the emergency path.

## Migration Safety

Envoy `migration-safety` task scans new migration files (those present in the new release but not in `current/database/migrations`) for destructive ops: `dropColumn`, `dropTable`, `renameColumn`, `Schema::drop`, `Schema::dropIfExists`, `Schema::rename`.

If any destructive op is found, the deploy aborts with the file list. Safety is bypassed by setting `MIGRATION_SAFETY_ALLOW=1` in `{root}/shared/.env` (NOT `.env.envoy` — this is a per-stage runtime gate, sticky between deploys until the operator removes it).

Bypass workflow:

```bash
ssh onidel 'echo MIGRATION_SAFETY_ALLOW=1 >> /var/www/{domain}/shared/.env'
vendor/bin/envoy run deploy --stage=test
ssh onidel 'sed -i /^MIGRATION_SAFETY_ALLOW=/d /var/www/{domain}/shared/.env'
```

Or set permanently in `.env.staging` / `.env.production` for projects that routinely run destructive migrations (rare).

The scan is heuristic — it greps source code, not parsed schema. False positives possible (e.g. comments mentioning `dropColumn`); review the flagged file before bypassing.

`init` story SKIPS migration-safety because there is no `current` to diff against.

## Health Check

With `HTTP_RUNTIME=octane`, `health-check` curls Octane directly so the assertion is "long-running app booted":

```bash
curl -s -o /dev/null -w "%{http_code}" --max-time 5 \
    -H "Host: {domain}" \
    http://127.0.0.1:{OCTANE_PORT}/up
```

Expected 200. Anything else fails the deploy and leaves the app in maintenance for operator intervention.

With `HTTP_RUNTIME=fpm`, Envoy curls `/up` through the configured Nginx vhost because PHP-FPM is not an Accelerator Supervisor process.

`/up` is Laravel's default health endpoint, configured via `bootstrap/app.php` `health: '/up'`.

## Prune-releases

Runs after a successful health-check + `maintenance-off`. Keeps `OPS_DEPLOY_KEEP_RELEASES` newest releases. Always preserves `current` even if it would have fallen off the list. Does NOT touch `archive/` (that's rollback evidence).

## Initial Deployment (Step-by-Step)

This is the full sequence for deploying a project to a fresh VPS for the first time.

### Fresh Project Happy Path

For AI agents and operators — the exact 3-command sequence for a fresh VPS:

```bash
# 1. Write Nginx (HTTP-only) + Supervisor config
vendor/bin/envoy run bootstrap --stage=prod

# 2. Deploy first release (app now accessible via HTTP)
vendor/bin/envoy run init --stage=prod

# 3. Obtain SSL cert + upgrade Nginx to HTTPS/HTTP2/HTTP3
vendor/bin/envoy run bootstrap-ssl --stage=prod
```

**Order matters.** `bootstrap` → `init` → `bootstrap-ssl`. Do NOT change the order:
- `bootstrap-ssl` REQUIRES `init` to have run first because `certbot --webroot` needs `{root}/current/public` to exist.
- `bootstrap` can run before `init` because it only writes Nginx/Supervisor config (no webroot needed).

### VPS One-Time Prerequisites

Before running the 3-command sequence above, ensure these are done once on the VPS:

1. `larahelp` v2.0+ installed at `/usr/local/bin/larahelp`:
   ```bash
   scp vendor/wireninja/accelerator/stubs/vps/larahelp onidel:/tmp/larahelp
   ssh onidel 'sudo mv /tmp/larahelp /usr/local/bin/larahelp && sudo chmod 755 /usr/local/bin/larahelp'
   ```
2. SSH key on VPS can clone from GitHub: `ssh onidel 'ssh -T git@github.com'`
3. `/etc/environment` has node/pnpm paths (for non-interactive SSH):
   ```bash
   ssh onidel 'echo "PATH=/home/adhi/.nvm/versions/node/v24.15.0/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin" | sudo tee /etc/environment'
   ```
4. `certbot` installed: `ssh onidel 'command -v certbot'`

### Step 1: Local Pre-flight

Before touching the VPS:

1. `.env.envoy` exists with `HTTP_RUNTIME`, required backend values, and explicit service flags.
2. `.env.staging` or `.env.production` exists, non-empty, key-compatible with `.env`.
3. Runtime env files have NO `OPS_DEPLOY_*` keys.
4. `pnpm run build` (or your configured npm bin) works locally without errors.
5. All application dependencies are committed (check `composer.json` and `package.json`).
6. Code is pushed to the remote repo branch configured in `OPS_DEPLOY_BRANCH`.

```bash
# Verify ports are free on VPS
ssh onidel 'ss -ltnp | grep -E ":(9012|9013)\b"'
```

### Step 2: Bootstrap (one-shot)

Writes Nginx vhost + Supervisor config to VPS. Run once per domain.

```bash
vendor/bin/envoy run bootstrap --stage=prod
```

What happens:
- `bootstrap-nginx`: Checks if SSL cert exists at `/etc/letsencrypt/live/{domain}/`. If yes → uses SSL+QUIC stub. If no → uses HTTP-only stub. Archives existing config before overwriting. Validates `nginx -t` and reloads.
- `bootstrap-supervisor`: Writes `/etc/supervisor/conf.d/{group}.conf` containing only enabled programs, or removes a previous group file when none are enabled; then runs `reread + update`.

**Guard**: If existing Nginx config has `ssl_certificate` but no cert file is found, bootstrap skips to avoid downgrading a working SSL config.

**Re-running bootstrap is safe**: If cert exists, it auto-uses the SSL stub. It won't downgrade HTTPS → HTTP.

### Step 3: First Deploy (init)

```bash
vendor/bin/envoy run init --stage=prod
```

What it does (in order):
1. `prepare-layout` — creates full directory structure, sets ownership + ACL, creates SQLite file if needed
2. `ensure-deploy-tools` — verifies git, composer, php, npm/pnpm, larahelp, setfacl, curl exist
3. `sync-env` — uploads `.env.staging`/`.env.production` to `shared/.env`
4. `clone-release` — clones repo at exact SHA
5. `link-shared` — symlinks `.env`, `storage`, `public/storage`
6. `build-release` — composer install + pnpm/bun/npm install + vite build
7. `harden-release` — chmod files
8. `prepare-laravel` — `larahelp --reoptimize`, `larahelp --setfacl`, `migrate --force`, `storage:link`
9. `switch-current` — atomic symlink swap
10. `invalidate-opcache` — per-file invalidation
11. `restart-service` — restart enabled Supervisor programs + FATAL check, or no-op for pure FPM
12. `health-check` — curl Octane directly or Nginx/PHP-FPM according to `HTTP_RUNTIME`

`init` intentionally SKIPS: db-backup (nothing to backup), maintenance-on (no traffic), migration-safety (no previous release), prune (no old releases).

After `init`, app is accessible via HTTP. Every enabled Supervisor program should be RUNNING; a pure FPM deployment with no optional services has no generated Supervisor programs.

### Step 4: SSL (one-shot, after init)

```bash
vendor/bin/envoy run bootstrap-ssl --stage=prod
```

**Dependency**: This REQUIRES `{root}/current/public` to exist. `init` must have completed successfully.

What happens:
- `obtain-cert`: Runs `certbot certonly --webroot -w {root}/current/public -d {domain}`. Non-interactive. Skips if cert already exists.
- `upgrade-nginx-ssl`: Uploads the SSL stub (HTTPS redirect, HTTP/2, HTTP/3/QUIC, `Alt-Svc` header), archives old HTTP config, validates `nginx -t`, reloads.

After this step, app is accessible via HTTPS with automatic HTTP→HTTPS redirect.

### Step 5: Verify

```bash
vendor/bin/envoy run status --stage={stage}
curl -I https://{domain}
```

Check:
- All enabled Supervisor programs RUNNING; a pure FPM deployment may have none
- HTTPS responds 200
- Dynamic assets accessible: `/livewire/livewire.min.js`, `/build/manifest.webmanifest`

## VPS Prerequisites

`prepare-layout` automatically handles these, but understanding is important:

- Creates `{root}/releases`, `{root}/shared`, `{root}/archive`
- Creates `shared/storage/framework/{views,cache,sessions}` (required by Blade/config cache)
- Creates `shared/storage/logs`, `shared/storage/app/public`
- Creates `shared/database/` directory
- Sets ownership: `chown -R {deploy_user}:{run_user}` on deploy root
- Applies ACL on `shared/storage` so runtime user can write from first boot
- If SQLite detected in `shared/.env`: creates the database file + sets ACL

**Ordering**: `prepare-layout` reads `shared/.env` for SQLite detection. In `init` story, `sync-env` runs before `prepare-layout` is implicitly handled (layout is created, then env synced, then release built). If SQLite file doesn't exist after `sync-env` + `prepare-layout`, the `prepare-laravel` task's `migrate` will fail. This is handled automatically by the `init` story ordering.

## Nginx Stub Architecture

Two stubs ship with the library:

| Stub | When used | Features |
|---|---|---|
| `nginx-vhost-http.conf.stub` | No SSL cert found | HTTP-only, certbot webroot challenge path, selected HTTP runtime locations |
| `nginx-vhost-ssl.conf.stub` | SSL cert exists | HTTPS redirect, HTTP/2+3, QUIC, Alt-Svc header, selected HTTP runtime locations |

With `HTTP_RUNTIME=octane`, both stubs render the **@octane named location pattern**:
```nginx
location / { try_files $uri @octane; }
location @octane {
    proxy_pass http://127.0.0.1:{port}$suffix;
    ...
}
```

With `HTTP_RUNTIME=fpm`, both stubs instead render PHP-FPM routing:

```nginx
index index.php;
location / { try_files $uri $uri/ /index.php?$query_string; }
location ~ \.php$ {
    fastcgi_pass unix:{FPM_SOCKET};
    ...
}
```

Static files are served directly in both modes. Reverb websocket routing is emitted only when `REVERB_ENABLED=true`.

`bootstrap-nginx` auto-detects which stub to use based on cert existence. Re-running `bootstrap` after SSL is set up will use the SSL stub (safe to re-run).

## larahelp

`larahelp` v2.0 is a shell script at `/usr/local/bin/larahelp`. Must be run from a Laravel project root (has `artisan` file).

Commands:
- `--reoptimize` — `optimize:clear` + `optimize` (used in deploy)
- `--setfacl` — sets ACL for storage, bootstrap/cache, and SQLite database (auto-detects from .env)
- `--log-size` — shows storage/logs disk usage
- `--log-truncate` — truncates laravel.log to last 1000 lines
- `--audit-permission` — finds world-writable or group-writable files
- `--audit-public` — finds unauthorized PHP files in public/

Environment variables respected:
- `OPS_DEPLOY_PHP_BIN` — PHP binary (default: `php`)
- `OPS_DEPLOY_RUN_USER` — runtime user for ACL (default: `www-data`)

To install/update larahelp on VPS:
```bash
scp vendor/wireninja/accelerator/stubs/vps/larahelp onidel:/tmp/larahelp
ssh onidel 'sudo mv /tmp/larahelp /usr/local/bin/larahelp && sudo chmod 755 /usr/local/bin/larahelp'
```

VPS-side, one-shot bootstrap (rendered from `.env.envoy`):

```bash
vendor/bin/envoy run bootstrap --stage=test
```

This writes `/etc/nginx/sites-available/{domain}.conf` (auto-detects HTTP-only or SSL+QUIC based on cert existence) and writes `/etc/supervisor/conf.d/{group}.conf` only when managed programs are enabled. With no managed programs, it removes a previous generated group file. Existing files are archived under `{root}/archive/` first.

See **Initial Deployment (Step-by-Step)** above for the full flow including VPS prerequisites.

## Continuous Deploy Checklist

Pre-flight (auto-checked, but operator should know):

- `current` symlink valid (`readlink -f current` returns a release path)
- `shared/.env` exists
- The selected runtime is healthy: Octane port listening in Octane mode, or PHP-FPM/Nginx serving `/up` in FPM mode
- Disk space free for at least 1 release + 1 backup

Run:

```bash
vendor/bin/envoy run deploy --stage=test
# Hot patch (no JS/CSS rebuild):
vendor/bin/envoy run deploy-slim --stage=test
```

Flow (sandbox to risky zone):

1. `ensure-deploy-tools` — verify git/composer/php/npm/larahelp/setfacl/curl exist.
2. `sync-env` — scp `.env.{stage}` to `{root}/shared/.env`, archive previous shared.
3. `clone-release` — git clone `--depth 1 --single-branch`, `git reset --hard {sha}`.
4. `link-shared` — symlink `.env`, `storage`, `public/storage`.
5. `build-release` — composer install + npm install + npm run build.
6. `harden-release` — chmod (excludes vendor for speed).
7. `clear-cache` — `optimize:clear` on current release (ensures fresh config for backup/migrate).

   *— production state from this point —*
8. `migration-safety` — grep new migration files for `dropColumn` / `dropTable` / `renameColumn`. Aborts unless `MIGRATION_SAFETY_ALLOW=1` is set in shared `.env`.
8. `db-backup` — Spatie pre-deploy profile.
9. `maintenance-on` — `php artisan down --secret={random}`.
10. `prepare-laravel` — `larahelp --reoptimize`, `larahelp --setfacl`, `migrate --force`.
11. `switch-current` — atomic symlink swap, archive previous.
12. `invalidate-opcache` — per-file opcache invalidate on the new release.
13. `restart-service` — restart enabled Supervisor programs + FATAL fail-fast, or no-op when none are enabled.
14. `health-check` — curl Octane directly or Nginx/PHP-FPM according to `HTTP_RUNTIME`; fail if not 200.
15. `maintenance-off` — `php artisan up`.
16. `prune-releases` — keep N latest, preserve current.

## Fresh Seed Redeploy

Use `deploy-fresh-seed` only when the operator intentionally wants to replace the database with fresh migrations and seeded data:

```bash
vendor/bin/envoy run deploy-fresh-seed --stage=test --i-understand-this-will-drop-and-reseed-database="aku mengkonfirmasi remigrate fresh seed"
```

This is not a migration-safety bypass. It intentionally runs `php artisan migrate:fresh --seed --force`, so all existing database data for that stage is destroyed after the pre-deploy backup.

Flow differences from `deploy`:

1. `assert-fresh-seed-confirmed` refuses to continue unless the exact long flag value is present.
2. `build-release-with-dev` requires `composer.lock` and runs `composer install` with dev dependencies available so seeders, factories, and Laravel's `fake()` helper can work, using the exact versions reviewed in development.
3. `migration-safety` is skipped because this story is already explicitly destructive.
4. `prepare-laravel-fresh-seed` reoptimizes the release under its real environment, then disables Laravel destructive-command protection only inside the confirmed one-shot PHP process before calling `migrate:fresh --seed --force`. It does not override `APP_ENV` or select an alternative runtime env file.
5. `prune-dev-dependencies` immediately runs `composer install` from the same required lockfile with `--no-dev --optimize-autoloader --classmap-authoritative`, removes bootstrap PHP cache files that may reference removed dev-only providers, then reoptimizes the app under its real production environment before `switch-current`.

Do not use this story for production unless the operator explicitly asks for data loss and accepts restoring from backup if seeders fail. If anything fails after `maintenance-on`, maintenance mode stays enabled for triage, matching the normal deploy failure posture.

## Rollback

```bash
vendor/bin/envoy run rollback --stage=test
```

Picks the newest release (excluding current) that has `vendor/autoload.php` AND a `.env` symlink. Skips incomplete releases (e.g. failed mid-build). Performs symlink swap + OPcache invalidate + enabled-service restart + health-check.

If no valid previous release exists, rollback aborts with a clear message — operator must restore from a backup zip manually.

## Releases Listing

```bash
vendor/bin/envoy run releases --stage=test
```

Output shape:

```
RELEASE                                      SIZE       AGE    STATUS
2026-05-19_10-45-12_a4383d3                  312M       2h     CURRENT
2026-05-19_08-12-34_8c9b1f0                  308M       5h     kept
...
2026-05-18_09-04-22_d8f3210                  295M       1d     will-prune

OPS_DEPLOY_KEEP_RELEASES=5
```

Use this before manual rollback or to confirm prune behaviour.

## Nginx Requirements

In Octane mode, the active Nginx vhost uses the `@octane` named location pattern:

```nginx
location / { try_files $uri @octane; }
location @octane {
    proxy_pass http://127.0.0.1:{OCTANE_PORT}$suffix;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    ...
}
```

In FPM mode, it renders `index index.php` plus `try_files $uri $uri/ /index.php?$query_string` with a PHP handler directed to the resolved FPM socket. The socket is derived from `OPS_DEPLOY_PHP_VERSION` plus optional `OPS_DEPLOY_{STAGE}_FPM_POOL` unless `OPS_DEPLOY_{STAGE}_FPM_SOCKET` is explicitly set. `/` resolves through Laravel rather than returning an Nginx directory-index 403.

Key properties:
- `server_name {domain}`
- `root {root}/current/public`
- static files served directly; dynamic requests routed through the selected HTTP runtime
- HTTPS redirect (301 from port 80) once SSL is active
- HTTP/2 + HTTP/3 (QUIC) with `Alt-Svc` header for SSL config
- Reverb websocket at `location ~ ^/(app|apps|pusher)/` only when Reverb is enabled
- Static asset caching with `expires 365d` + `Cache-Control: public, immutable`
- Domain-specific access/error logs
- `client_max_body_size 110m`, leaving multipart overhead above the 100 MB application file limit

Do NOT:
- Use `upstream` block + `proxy_pass` directly in `location /` (old pattern)
- Add a PHP-FPM handler while `HTTP_RUNTIME=octane`; the generated handler is required only in FPM mode
- Point Nginx to `{root}/html/public` after migrating to release layout
- Manually run `certbot --nginx` (use `bootstrap-ssl` instead to avoid QUIC conflicts)

## Supervisor Requirements

- When at least one managed service is enabled, one file: `/etc/supervisor/conf.d/{group}.conf`
- Pure FPM deployments without optional managed services intentionally have no generated Supervisor group file
- Run commands from `{root}/current`
- Log to `{root}/shared/storage/logs/{service}.log`
- Run as `www-data` (or configured run user)
- One group containing only this stage's programs
- Never use `--workers=auto` or `--task-workers=auto`; keep explicit counts in `.env.envoy`.
- For Swoole, task workers default to `0`. Set a positive count before using Octane task dispatch/concurrency.
- NEVER use generic names (`octane`, `horizon`, `reverb`)

## OPcache

- Per-release `opcache_invalidate()` during deploy (already done in `invalidate-opcache`).
- Do NOT use global `opcache_reset()` as a deploy default — OPcache may be shared with unrelated apps.
- If `opcache.validate_timestamps=false`, code changes require deploy invalidation + service restart.

## Cleanup Safety

Only clean inside the configured project root.

Safe after verification:

- old non-current releases (auto-handled by `prune-releases`)
- legacy `{root}/html`
- legacy deploy runner folders
- stale env backups
- archived old generated configs
- stale `current.*` symlinks pointing to deleted releases

Preserve or snapshot before deleting:

- active Nginx config
- active Supervisor config
- current release
- shared `.env`
- shared storage
- `archive/` snapshots until rollback verified

Never delete shared app uploads casually. Check `{root}/shared/storage/app` first.

## Audit Checklist

Local:

- `.env.envoy` has `HTTP_RUNTIME`, its required backend values, and explicit enabled-service flags
- Octane stages have `OCTANE_PORT` and explicit Octane worker counts
- target stage is enabled
- runtime seed exists and has no `OPS_DEPLOY_*`
- `.env`, `.env.staging`, `.env.production` have compatible key sets
- `DB_SOCKET` is not active for SQLite

Remote:

- Nginx config passes `nginx -t`
- Nginx root is `{root}/current/public`
- enabled stage ports are owned only by this project after deploy
- Supervisor group names are stage-scoped when managed programs are enabled
- `{root}/current` points to an existing release with `vendor/autoload.php` and `.env` symlink
- `{root}/shared/.env` exists and has no `OPS_DEPLOY_*`
- SQLite DB, if used, is under `{root}/shared/database`
- dynamic package assets return HTTP 200

## Verification Commands

```bash
vendor/bin/envoy tasks
vendor/bin/envoy run status --stage=test
vendor/bin/envoy run releases --stage=test
ssh <host> 'sudo nginx -t'
ssh <host> 'sudo supervisorctl status {group}:*' # only when managed programs are enabled
ssh <host> 'readlink -f {root}/current'
ssh <host> "ss -ltnp | grep -E ':(9012|9013|2412)\b'" # only enabled service ports
curl -I -L https://{domain}
```

Backup status JSON for AI agents:

```bash
php artisan vps:backup-status --json --compact
```

## Breaking Changes

See the `accelerator-breaking-changes` skill for the full migration guide when upgrading `wireninja/accelerator`.

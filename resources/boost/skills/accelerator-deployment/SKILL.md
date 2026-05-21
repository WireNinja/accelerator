---
name: accelerator-deployment
description: Deploy Laravel apps with WireNinja Accelerator Envoy release flow — first-time init, continuous deploy with maintenance window, db backup, health check, prune-releases, rollback validation, and Supervisor / Nginx safety.
---

# Accelerator Deployment

## When To Use

First deployment, continuous deployment, deployment cleanup, Envoy release folders, `.env.envoy` / `.env.staging` / `.env.production`, Nginx, Supervisor, Octane, Reverb, Horizon, Scheduler, Nightwatch, OPcache, `larahelp`, `setfacl`, db backup, maintenance mode, health checks, release prune, rollback.

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

## Stories Cheatsheet

| Story | When | What it runs |
|---|---|---|
| `bootstrap` | one-shot per VPS to write Nginx vhost + Supervisor conf | bootstrap-nginx + bootstrap-supervisor |
| `init` | first deploy on a fresh VPS root | layout setup + tooling check + clone + build + harden + prepare-laravel + switch + opcache + restart + health-check |
| `deploy` | continuous full deploy | tooling + sync-env + clone + build + harden + clear-cache + migration-safety + db-backup + maintenance-on + prepare-laravel + switch + opcache + restart + health-check + maintenance-off + prune |
| `deploy-slim` | hot patch backend only (no JS/CSS rebuild) | same as deploy minus build-release |
| `rollback` | switch back to previous valid release | rollback-release + opcache + restart + health-check (NO maintenance window — speed prioritised) |
| `releases` | list release history + prune target | tabular output |
| `backups` | list predeploy and scheduled backup files | list-backups |
| `status` | quick state check | readlink current + supervisor status |
| `restart` / `logs` | targeted service operation | supervisorctl / tail |

`init` SKIPS db-backup, maintenance, prune, and migration-safety by design — the first deploy has no `current` symlink, no DB rows worth backing up, and no old releases to remove.

## Minimal Project Files

```text
Envoy.blade.php
.env.envoy
.env.staging
.env.production
```

`Envoy.blade.php` only defines server aliases and imports the package bridge:

```blade
@servers(['vps' => ['onidel'], 'localhost' => '127.0.0.1'])

@import('vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php')
```

Do not copy deployment shell scripts into the project.

## Required `.env.envoy` Keys

Per stage (TEST and PROD), `_OCTANE_PORT` is REQUIRED. Health check curls Octane directly.

Global keys:

- `OPS_DEPLOY_DEFAULT_STAGE`, `OPS_DEPLOY_PROJECT`, `OPS_DEPLOY_SSH_HOST`
- `OPS_DEPLOY_REPO`, `OPS_DEPLOY_BRANCH`
- `OPS_DEPLOY_KEEP_RELEASES` (default 5)
- `OPS_DEPLOY_PHP_BIN`, `OPS_DEPLOY_NPM_BIN` (supports `pnpm`, `bun`, or `npm`; auto-detects if empty — fallback order: pnpm → bun → npm)
- `OPS_DEPLOY_RUN_USER` (default `www-data`)
- `OPS_DEPLOY_SSL_EMAIL`

Per stage (`TEST` / `PROD`):

- `OPS_DEPLOY_{STAGE}_ENABLED`
- `OPS_DEPLOY_{STAGE}_DOMAIN`, `OPS_DEPLOY_{STAGE}_ROOT`
- `OPS_DEPLOY_{STAGE}_GROUP` (Supervisor group, stage-scoped)
- `OPS_DEPLOY_{STAGE}_RUNTIME` (`swoole` etc.)
- `OPS_DEPLOY_{STAGE}_OCTANE_PORT` **REQUIRED**
- `OPS_DEPLOY_{STAGE}_REVERB_PORT`
- `OPS_DEPLOY_{STAGE}_NIGHTWATCH_PORT`
- `OPS_DEPLOY_{STAGE}_NIGHTWATCH_ENABLED`

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
{group}_reverb
{group}_scheduler
{group}_nightwatch  (only when stage explicitly enables it)
```

Examples for `wss_test`:

```text
wss_test:wss_test_octane
wss_test:wss_test_horizon
wss_test:wss_test_reverb
wss_test:wss_test_scheduler
```

Nightwatch is opt-in. Do not add it unless the stage flag enables it.

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

`rollback` does NOT use a maintenance window. Octane restart < 5s; nginx queues requests during restart. Adding maintenance overlay just slows down the emergency path.

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

`health-check` curls Octane directly (NOT through Nginx) so the assertion is "app booted", not "proxy still serves cached page":

```bash
curl -s -o /dev/null -w "%{http_code}" --max-time 5 \
    -H "Host: {domain}" \
    http://127.0.0.1:{OCTANE_PORT}/up
```

Expected 200. Anything else fails the deploy and leaves the app in maintenance for operator intervention.

`/up` is Laravel's default health endpoint, configured via `bootstrap/app.php` `health: '/up'`.

## Prune-releases

Runs after a successful health-check + `maintenance-off`. Keeps `OPS_DEPLOY_KEEP_RELEASES` newest releases. Always preserves `current` even if it would have fallen off the list. Does NOT touch `archive/` (that's rollback evidence).

## First-Time Deploy Checklist

Before touching the VPS:

1. Read `.env.envoy` — confirm domain, root, group, ports, repo, branch, PHP/npm binaries, run user.
2. Confirm `.env.staging` / `.env.production` exists, is non-empty, and key-compatible with `.env`.
3. Confirm runtime env files have NO `OPS_DEPLOY_*` keys.
4. Confirm `OPS_DEPLOY_{STAGE}_OCTANE_PORT` is set (required).
5. Confirm ports are not used by another project on the VPS:

   ```bash
   ssh onidel 'ss -ltnp'
   ```
6. For SQLite, keep `DB_SOCKET`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, `DB_PASSWORD` commented or empty.

VPS-side, one-shot bootstrap (rendered from `.env.envoy`):

```bash
vendor/bin/envoy run bootstrap --stage=test
```

This writes `/etc/nginx/sites-available/{domain}.conf` (HTTP-only Octane upstream + Reverb proxy + static asset locations) and `/etc/supervisor/conf.d/{group}.conf` (stage-scoped programs), validates `nginx -t`, runs `supervisorctl reread && update`. Existing files are archived under `{root}/archive/` first.

Manual operator follow-ups (one-time):

1. Run certbot for SSL: `sudo certbot --nginx -d {domain} -m {OPS_DEPLOY_SSL_EMAIL} --agree-tos --redirect`.
2. Confirm `larahelp` is in `/usr/local/bin/larahelp`.
3. Confirm SSH key on the VPS can clone from GitHub (test once with `ssh -T git@github.com`).

Then run from local:

```bash
vendor/bin/envoy run init --stage=test
vendor/bin/envoy run status --stage=test
```

Verify HTTPS, Livewire/Filament dynamic assets, Reverb websocket routes, OPcache state, and service logs.

## Continuous Deploy Checklist

Pre-flight (auto-checked, but operator should know):

- `current` symlink valid (`readlink -f current` returns a release path)
- `shared/.env` exists
- Octane port still listening
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
13. `restart-service` — supervisor restart + sleep 2 + grep FATAL fail-fast.
14. `health-check` — curl Octane `/up`, fail if not 200.
15. `maintenance-off` — `php artisan up`.
16. `prune-releases` — keep N latest, preserve current.

## Rollback

```bash
vendor/bin/envoy run rollback --stage=test
```

Picks the newest release (excluding current) that has `vendor/autoload.php` AND a `.env` symlink. Skips incomplete releases (e.g. failed mid-build). Performs symlink swap + opcache invalidate + supervisor restart + health-check.

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

The active Nginx vhost should:

- `server_name {domain}`
- `root {root}/current/public`
- HTTPS redirect once SSL is ready
- HTTP/2 + HTTP/3 when supported
- proxy normal Laravel requests to Octane on the stage Octane port
- proxy Reverb websocket endpoints to the stage Reverb port
- send `X-Forwarded-Host`, `X-Forwarded-Proto`, `X-Forwarded-Port`, `X-Forwarded-For`, `Host` headers
- avoid blocking dynamic route-backed assets (Livewire, Filament JS)
- log to domain-specific access/error files

Verify dynamic assets:

```text
/livewire/livewire.min.js
/build/manifest.webmanifest
/sw.js
```

Never point Nginx to `{root}/html/public` after migrating to release layout.

## Supervisor Requirements

- One file: `/etc/supervisor/conf.d/{group}.conf`
- Run commands from `{root}/current`
- Log to `{root}/shared/storage/logs/{service}.log`
- Run as `www-data` (or configured run user)
- One group containing only this stage's programs
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

- `.env.envoy` has the stage keys including OCTANE_PORT
- target stage is enabled
- runtime seed exists and has no `OPS_DEPLOY_*`
- `.env`, `.env.staging`, `.env.production` have compatible key sets
- `DB_SOCKET` is not active for SQLite

Remote:

- Nginx config passes `nginx -t`
- Nginx root is `{root}/current/public`
- stage ports are owned only by this project after deploy
- Supervisor group names are stage-scoped
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
ssh <host> 'sudo supervisorctl status {group}:*'
ssh <host> 'readlink -f {root}/current'
ssh <host> "ss -ltnp | grep -E ':(9012|9013|2412)\b'"
curl -I -L https://{domain}
```

Backup status JSON for AI agents:

```bash
php artisan vps:backup-status --json --compact
```

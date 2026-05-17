---
name: accelerator-deployment
description: Deploy Laravel apps with WireNinja Accelerator Envoy release flow, first-time VPS setup, shared env seeding, larahelp, Supervisor, Nginx, OPcache, rollback, and cleanup.
---

# Accelerator Deployment

## When To Use

Use this skill for first deployment, continuous deployment, deployment cleanup, Envoy release folders, `.env.envoy`, `.env.staging`, `.env.production`, Nginx, Supervisor, Octane, Reverb, Horizon, Scheduler, Nightwatch, OPcache, `larahelp`, or `setfacl` work in a WireNinja Accelerator Laravel project.

## Non-Negotiable Rules

- Envoy is the deployment orchestrator.
- Do not bootstrap Laravel config from Envoy.
- Envoy reads deploy config directly from project-root `.env.envoy`.
- `OPS_DEPLOY_*` keys belong only in `.env.envoy`, never in `.env`, `.env.staging`, `.env.production`, `.env.example`, or `.base-env.example`.
- Runtime env seeding comes from `.env.staging` for `test` and `.env.production` for `prod`.
- Envoy syncs the selected runtime env seed to `{root}/shared/.env` on every deploy.
- Use `larahelp --reoptimize` and `larahelp --setfacl` directly. Do not add fallback abstractions.
- Scope every SSH command to the configured domain, root, group, and ports.
- Never touch unrelated domains, Nginx files, Supervisor groups, `/var/www` roots, ports, or services.
- Do not serve Laravel from legacy `{root}/html/public`; Nginx must serve `{root}/current/public`.

## Minimal Project Files

Project userland should stay thin:

```text
Envoy.blade.php
.env.envoy
.env.staging
.env.production
```

`Envoy.blade.php` should only define server aliases and import the package Envoy file:

```blade
@servers(['vps' => ['onidel'], 'localhost' => '127.0.0.1'])

@import('vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php')
```

Do not copy deployment shell scripts into the project.

## Required Local Inputs

`.env.envoy` contains deployment wiring:

- `OPS_DEPLOY_DEFAULT_STAGE`
- `OPS_DEPLOY_PROJECT`
- `OPS_DEPLOY_SSH_HOST`
- `OPS_DEPLOY_REPO`
- `OPS_DEPLOY_BRANCH`
- `OPS_DEPLOY_PHP_BIN`
- `OPS_DEPLOY_BUN_BIN`
- `OPS_DEPLOY_RUN_USER`
- `OPS_DEPLOY_SSL_EMAIL`
- per-stage enabled flag
- per-stage domain
- per-stage root
- per-stage Supervisor group
- per-stage runtime
- per-stage Octane port
- per-stage Reverb port
- per-stage Nightwatch port
- per-stage Nightwatch enabled flag

`.env.staging` and `.env.production` contain runtime application secrets and must be key-compatible with `.env`.

For SQLite deployments, the database file must be shared across releases:

```dotenv
DB_CONNECTION=sqlite
DB_DATABASE=/var/www/example.com/shared/database/database.sqlite
```

Create `{root}/shared/database` on first deploy and seed or move the live SQLite file there before switching traffic. Do not leave SQLite at `database/database.sqlite` inside a release.

## Server Layout

Expected root:

```text
{root}/archive
{root}/current -> {root}/releases/{release}
{root}/releases/{release}
{root}/shared/.env
{root}/shared/storage
```

Shared Laravel-derived links:

```text
release/.env            -> {root}/shared/.env
release/storage         -> {root}/shared/storage
release/public/storage  -> {root}/shared/storage/app/public
```

Release folder format:

```text
YYYY-MM-DD_HH-MM-SS_shortsha
```

Example:

```text
2026-05-16_23-02-01_a4383d3
```

## Service Naming

Supervisor group comes from `.env.envoy`:

```text
OPS_DEPLOY_TEST_GROUP=wss_test
OPS_DEPLOY_PROD_GROUP=wss_prod
```

Programs are group-prefixed:

```text
{group}_octane
{group}_horizon
{group}_reverb
{group}_scheduler
{group}_nightwatch
```

Examples:

```text
wss_test:wss_test_octane
wss_test:wss_test_horizon
wss_test:wss_test_reverb
wss_test:wss_test_scheduler
```

Nightwatch is opt-in. Do not create or run it unless stage config explicitly enables it.

## First-Time Deploy Checklist

Before touching the VPS:

1. Read `.env.envoy` and identify the exact stage.
2. Confirm domain, root, group, ports, repo, branch, PHP binary, Bun binary, and run user.
3. Confirm `.env.staging` or `.env.production` exists and is non-empty.
4. Confirm runtime env files have no `OPS_DEPLOY_*` keys.
5. Confirm ports are not used by another project by running `ss -ltnp` on the VPS.
6. Confirm the target root belongs to the intended project.
7. Confirm `.env`, `.env.staging`, and `.env.production` have compatible keys.
8. Confirm database-specific keys are intentional. For SQLite, keep `DB_SOCKET`, `DB_HOST`, `DB_PORT`, `DB_USERNAME`, and `DB_PASSWORD` commented or empty.

On the VPS:

1. Create `{root}`, `{root}/releases`, `{root}/shared`, `{root}/archive`, and `{root}/shared/storage`.
2. Prepare Nginx for the domain and point it to `{root}/current/public`.
3. Prepare Supervisor config with stage-scoped names.
4. Run `sudo nginx -t` before reload.
5. Run `sudo supervisorctl reread` and `sudo supervisorctl update` after Supervisor config changes.
6. Run `vendor/bin/envoy run init --stage=test`.
7. Run `vendor/bin/envoy run status --stage=test`.
8. Verify HTTPS, Livewire/Filament dynamic assets, Reverb websocket routes, OPcache state, and service logs.

Initial deploy still needs human-owned secrets in runtime env seed files. Do not invent production secrets.

Port assignment is human/agent-owned. `.env.envoy` stores the selected values, but it does not know what the VPS already uses. Pick a contiguous project range only after checking the VPS:

```bash
ssh onidel 'ss -ltnp'
```

Example:

```dotenv
OPS_DEPLOY_PROD_OCTANE_PORT=9020
OPS_DEPLOY_PROD_REVERB_PORT=9021
OPS_DEPLOY_PROD_NIGHTWATCH_PORT=2420
```

## Continuous Deploy Checklist

Use:

```bash
vendor/bin/envoy run deploy --stage=test
```

Use slim deploy only when frontend assets do not need rebuilding:

```bash
vendor/bin/envoy run deploy-slim --stage=test
```

Deploy flow:

1. Verify local `.env.envoy`.
2. Resolve remote Git SHA.
3. Verify required tools on VPS: `git`, Composer, configured PHP, configured Bun, `larahelp`, `setfacl`.
4. Sync `.env.staging` or `.env.production` to `{root}/shared/.env`.
5. Clone release folder.
6. Link shared `.env`, `storage`, and `public/storage`.
7. Install Composer dependencies.
8. Build frontend unless slim deploy.
9. Harden permissions.
10. Run `larahelp --reoptimize`.
11. Run `larahelp --setfacl`.
12. Run migrations.
13. Switch `{root}/current`.
14. Invalidate OPcache per PHP file in the new release.
15. Restart Supervisor group.

The selected runtime seed is synced every deploy:

```text
.env.staging    -> {root}/shared/.env for test
.env.production -> {root}/shared/.env for prod
```

After adopting this flow, do not treat remote `{root}/shared/.env` as the source of truth. Edit the local seed and deploy again.

## Nginx Requirements

The active Nginx config should:

- use `server_name {domain}`
- set `root {root}/current/public`
- redirect HTTP to HTTPS once SSL is ready
- support HTTP/2 and HTTP/3 when the server supports it
- proxy normal Laravel requests to Octane on the stage Octane port
- proxy Reverb websocket endpoints to the stage Reverb port
- avoid blocking dynamic route-backed assets such as Livewire JavaScript
- log to domain-specific access/error files

Verify dynamic assets that are generated or served by Laravel packages:

```text
/livewire/livewire.min.js
/build/manifest.webmanifest
/sw.js
```

Never point Nginx to:

```text
{root}/html/public
```

## Supervisor Requirements

Supervisor config should:

- live under `/etc/supervisor/conf.d/{group}.conf`
- run commands from `{root}/current`
- log to `{root}/shared/storage/logs/{service}.log`
- use `www-data` or the configured run user
- include one group containing only this stage's programs
- avoid generic names like `octane`, `horizon`, or `reverb`

## OPcache

- Invalidate OPcache per PHP file in the new release.
- Do not use global `opcache_reset()` as the default because OPcache may be shared with unrelated apps.
- If `opcache.validate_timestamps=false`, code changes require per-release invalidation plus service restart.

## Rollback

Use:

```bash
vendor/bin/envoy run rollback --stage=test
```

Rollback changes the `current` symlink, invalidates OPcache for current, and restarts the Supervisor group.

## Cleanup

Only clean inside the configured project root.

Safe after verification:

- old non-current releases
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

Never delete shared app uploads casually. Check `{root}/shared/storage/app` before removing anything.

## Audit Checklist

Local:

- `.env.envoy` has the required stage keys
- target stage is enabled
- runtime seed exists and has no `OPS_DEPLOY_*`
- `.env`, `.env.staging`, and `.env.production` have compatible key sets
- `DB_SOCKET` is not active for SQLite
- Reverb bind keys are intentional

Remote:

- Nginx config passes `nginx -t`
- Nginx root is `{root}/current/public`
- stage ports are owned only by this project after deploy
- Supervisor group names are stage-scoped
- `{root}/current` points to an existing release
- `{root}/shared/.env` exists and has no `OPS_DEPLOY_*`
- SQLite DB, if used, is under `{root}/shared/database`
- dynamic package assets return HTTP 200

Preferred future machine-readable audit shape:

```json
{
  "status": "pass",
  "checks": [
    {
      "id": "remote.current.exists",
      "severity": "error",
      "status": "pass",
      "message": "current points to an existing release."
    }
  ]
}
```

## Verification Commands

```bash
vendor/bin/envoy tasks
vendor/bin/envoy run status --stage=test
ssh <host> 'sudo nginx -t'
ssh <host> 'sudo supervisorctl status {group}:*'
ssh <host> 'readlink -f {root}/current'
ssh <host> 'ss -ltnp | grep -E ":(octane|reverb|nightwatch ports)\b"'
curl -I -L https://{domain}
```

Expected:

- `vps:backup-status` may exist
- Nginx points to `{root}/current/public`
- only intended stage services are running
- current release exists
- shared env has no `OPS_DEPLOY_*` keys
- Livewire/Filament dynamic assets return HTTP 200

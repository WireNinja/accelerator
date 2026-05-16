---
name: accelerator-deployment
description: Work with WireNinja Accelerator Envoy deployment, release folders, shared env seeding, larahelp, Supervisor, Nginx, and OPcache invalidation.
---

# Accelerator Deployment

## When To Use

Use this skill when working on WireNinja Accelerator deployment automation, `Envoy.blade.php`, release folders, shared `.env` seeding, `larahelp`, Supervisor, Nginx, OPcache invalidation, rollback, or cleanup of legacy deployment paths.

## Core Rules

- Envoy is the deploy orchestrator.
- Do not call `php artisan ops:*` from Envoy.
- Do not bootstrap Laravel config from Envoy.
- Envoy reads deployment values directly from project-root `.env.envoy`.
- `.env.envoy` contains only `OPS_DEPLOY_*` keys and must not be committed.
- Runtime `.env` seeding comes from local `.env.staging` for `test` and local `.env.production` for `prod`; these files must not be committed.
- Envoy syncs the selected local env seed file to `{root}/shared/.env` on every deploy, not only during `init`.
- Use `larahelp` directly for Laravel optimize and ACL work. Do not hide deploy behind fallback helper abstractions.
- Scope every SSH operation to the configured stage root/domain/group.
- Do not touch unrelated domains, projects, Nginx configs, Supervisor groups, or `/var/www` paths.

## Primary Commands

```bash
vendor/bin/envoy run init --stage=test
vendor/bin/envoy run deploy --stage=test
vendor/bin/envoy run deploy-slim --stage=test
vendor/bin/envoy run status --stage=test
vendor/bin/envoy run restart --stage=test --service=all
vendor/bin/envoy run logs --stage=test --service=octane
vendor/bin/envoy run rollback --stage=test
```

`--env=test` is also accepted for legacy muscle memory, but prefer `--stage=test`.

## Env Files

Local deploy config:

```text
.env.envoy
```

Local runtime env seed files:

```text
.env.staging
.env.production
```

Server runtime env:

```text
{root}/shared/.env
```

Never commit these files.

## Release Layout

Expected server layout:

```text
{root}/archive
{root}/current -> {root}/releases/{release}
{root}/releases/{release}
{root}/shared/.env
{root}/shared/storage
```

Nginx must serve:

```text
{root}/current/public
```

Do not serve from a legacy `{root}/html/public` path after migration.

## Deploy Flow

Envoy deploy should:

- verify required tools: `git`, `composer`, configured PHP binary, configured Bun binary, `larahelp`, and `setfacl`
- create a new ISO-like release folder
- clone the configured repository and branch
- sync the selected local env seed file to `{root}/shared/.env`
- link `{root}/shared/.env` to release `.env`
- link `{root}/shared/storage` to release `storage`
- link release `public/storage` to `{root}/shared/storage/app/public`
- run Composer install
- run Bun install/build unless using slim deploy
- harden permissions
- run `larahelp --reoptimize`
- run `larahelp --setfacl`
- run Laravel migration and storage linking as normal application deploy work
- switch `{root}/current` to the new release
- invalidate OPcache per PHP file in the new release
- restart the configured Supervisor group

## Initial Deploy

For first deployment:

1. Prepare `.env.envoy` with only `OPS_DEPLOY_*` keys.
2. Prepare `.env.staging` or `.env.production` locally with the runtime app secrets.
3. Run `vendor/bin/envoy run init --stage=test`.

`init` creates the remote layout, then runs the same release deploy. The deploy flow syncs the selected local env seed file to `{root}/shared/.env`.

## Server Safety

- Archive replaced symlinks with `mv` into `{root}/archive`.
- Do not delete release folders or archives from automated tasks.
- Rollback changes only the `current` symlink and restarts the configured Supervisor group.
- Use `sudo nginx -t` before any manual Nginx reload.
- Use `sudo supervisorctl status {group}:*` for service status.

## Verification Checklist

After deployment:

```bash
vendor/bin/envoy run status --stage=test
ssh <host> 'sudo nginx -t'
ssh <host> 'readlink -f {root}/current'
curl -I -L https://{domain}
```

Confirm:

- active release points at the expected commit
- shared `.env` exists and is readable by the runtime user
- `storage` and `bootstrap/cache` are writable by the runtime user
- Supervisor services are running
- dynamic route-backed assets such as Livewire assets return HTTP 200 behind Nginx

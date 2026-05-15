---
name: accelerator-deployment
description: Work with WireNinja Accelerator deployment automation, Envoy bridges, release folders, OPcache invalidation, Supervisor, Nginx, and stage config.
---

# Accelerator Deployment

## When To Use

Use this skill when working on WireNinja Accelerator deployment automation, `ops:*` commands, release folders, Envoy deployment bridges, Nginx/Supervisor config, Octane deploy behavior, OPcache invalidation, or cleanup of legacy deployment paths.

## Core Rules

- Read deployment behavior from `config('accelerator.deploy')`.
- Never call `env()` directly outside config files.
- Keep config env-driven; do not require applications to publish Accelerator config just to use new keys.
- Default stages are `test` and `prod`; the default stage is `test`.
- Scope all deploy and cleanup operations to the configured stage/domain/root.
- Do not touch unrelated domains, projects, Nginx configs, Supervisor groups, or `/var/www` paths.

## Commands

Use these Artisan commands instead of project-local shell scripts:

```bash
php artisan ops:env-check --stage=test
php artisan ops:init-server --stage=test
php artisan ops:init-env --stage=test
php artisan ops:deploy --stage=test
php artisan ops:status --stage=test
php artisan ops:restart all --stage=test
php artisan ops:logs octane --stage=test
php artisan ops:rollback --stage=test
```

Use `--stage=prod` only when the project explicitly intends to operate on production.

## Envoy Bridge

Project `Envoy.blade.php` files should stay thin:

- define `@servers`
- set defaults for `$stage`, `$service`, and `$path`
- import `vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php`
- optionally alias stable story names like `deploy`, `status`, `restart`, and `logs`

Do not reintroduce copied deployment shell scripts into applications.

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

Do not serve from:

```text
{root}/html/public
```

## Shared Files

Share Laravel-derived runtime paths, not build output:

- `.env`
- `storage`
- `public/storage -> shared/storage/app/public`

Do not share Vite build output between releases.

## Cleanup Policy

During migration or risky cleanup:

- archive legacy paths with `mv` into `{root}/archive`
- keep old release folders until rollback is no longer needed
- keep archived Nginx and Supervisor configs until the new release has been verified
- delete only when the user explicitly allows deletion and the target is confirmed to belong to the active project

Archive folder deletion is not automatically safe. It may contain:

- old `current` symlink targets
- previous Nginx/Supervisor configs
- legacy `html` paths
- deploy runners used for audit/debugging

## Nginx And Supervisor

- Supervisor group and program names must be stage-scoped.
- Validate Nginx with `nginx -t` before reload.
- Archive replaced Nginx config and enabled symlink before writing new ones.
- Only one enabled Nginx config should match a domain.
- Restart only the configured Supervisor group, for example `wss_test:*`.

## OPcache

Invalidate PHP files in the new release before service restart.

Use per-file `opcache_invalidate()` over the release path. Do not use global `opcache_reset()` as the default deploy strategy because it can affect unrelated PHP applications sharing the same OPcache process.

## Self-Hosted Package Updates

When the deployment generator itself changes inside Accelerator, the first deploy from an old release may still render Nginx or Supervisor config with the old package code before `current` moves to the new release.

After upgrading Accelerator deployment code:

- deploy once to move `current` to the release that contains the new package
- verify generated config
- deploy a second time if the first deploy still rendered config from the previous release

Record this explicitly in the deployment notes so future operators do not confuse it with an application deploy failure.

## Nightwatch

Nightwatch is opt-in:

- configure an explicit host and port
- include it only when the stage enables the `nightwatch` service
- do not assume a default Nightwatch process exists

## Verification Checklist

After deployment or cleanup, verify:

```bash
php artisan ops:status --stage=test
sudo nginx -t
readlink -f {root}/current
curl -I -L https://{domain}
```

Confirm:

- active release points at the expected commit
- shared `.env` is present
- Supervisor services are running
- exactly one enabled Nginx config matches the domain
- the domain returns the expected HTTP status
- dynamic route-backed JavaScript such as Livewire assets returns HTTP 200 behind Nginx

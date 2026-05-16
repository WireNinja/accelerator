## WireNinja Accelerator

WireNinja Accelerator provides reusable Laravel application conventions, built-in middleware, Filament presets, and deployment automation.

### Configuration

- Prefer reading Accelerator behavior from `config('accelerator.*')`.
- Do not call `env()` directly outside config files.
- Accelerator config is intentionally env-driven so applications can use new package config keys without publishing config files in every project.
- Deployment config defaults to two stages: `test` and `prod`.
- The default deployment stage is `test`.

### Deployment

- Use Envoy as the deployment orchestrator.
- Do not call `php artisan ops:*` from Envoy.
- Do not bootstrap Laravel config from Envoy.
- Envoy reads deploy configuration from project-root `.env.envoy`, which contains only `OPS_DEPLOY_*` keys and must not be committed.
- Use `.env.staging` and `.env.production` as local-only runtime env seed files for first deploy; do not commit them.
- Use the package Envoy bridge at `vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php`; project Envoy files should only provide server aliases.
- Use `vendor/bin/envoy run deploy --stage=test` for release-based deployments.
- Use `vendor/bin/envoy run init --stage=test` for first deploy when seeding `{root}/shared/.env` from the local env seed file.

### Release Layout

- Deployments use ISO-like release folders under `{root}/releases`.
- `{root}/current` must point to the active release.
- `{root}/shared/.env` is the runtime env source.
- `{root}/shared/storage` is shared between releases.
- Public storage should link to `{root}/shared/storage/app/public`.
- Do not serve Laravel from a legacy `{root}/html/public` path after migration to release layout.

### Server Safety

- Scope production operations to the configured stage/domain/root.
- Do not touch unrelated domains or projects from a deployment command.
- Archive replaced Nginx and Supervisor files before overwriting them.
- Keep `{root}/archive` while a deployment is being proven; it contains rollback evidence and archived legacy paths.
- Do not delete deploy archives blindly. Prune archives only after the active release, rollback path, Nginx config, and Supervisor services are verified.
- For SSH cleanup during migration, prefer moving legacy paths into `{root}/archive` instead of deleting them.

### Nginx, Supervisor, And Runtime

- Supervisor program names must be stage-scoped, such as `wss_test_octane`, to avoid cross-project collisions.
- Nginx config should point to `{root}/current/public`.
- Validate Nginx with `nginx -t` before reload.
- Invalidate OPcache per PHP file in the new release before restarting services; do not use global `opcache_reset()` as a deploy default.
- Nightwatch is opt-in and must have explicit host/port config.
- Trust proxy behavior belongs in Accelerator built-in middleware, not duplicated in user-land application bootstrap.

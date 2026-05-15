## WireNinja Accelerator

WireNinja Accelerator provides reusable Laravel application conventions, built-in middleware, Filament presets, and deployment automation.

### Configuration

- Prefer reading Accelerator behavior from `config('accelerator.*')`.
- Do not call `env()` directly outside config files.
- Accelerator config is intentionally env-driven so applications can use new package config keys without publishing config files in every project.
- Deployment config defaults to two stages: `test` and `prod`.
- The default deployment stage is `test`.

### Deployment

- Use `php artisan ops:*` commands for deployment work instead of project-local shell scripts.
- Use `php artisan ops:deploy` for release-based deployments.
- Use `php artisan ops:status` to inspect the active release, shared env, Supervisor group, and service status.
- Use `php artisan ops:restart {service}` to restart `all`, `octane`, `horizon`, `reverb`, `scheduler`, or `nightwatch`.
- Use `php artisan ops:logs {service}` to read service logs from shared storage.
- Use `php artisan ops:rollback` instead of manually changing the `current` symlink.
- Use the package Envoy bridge at `vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php`; project Envoy files should only provide server/path defaults and stable story aliases.

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

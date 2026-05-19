## WireNinja Accelerator

WireNinja Accelerator provides reusable Laravel application conventions, built-in middleware, Filament presets, and deployment automation.

### Configuration

- Prefer reading Accelerator behavior from `config('accelerator.*')`.
- Do not call `env()` directly outside config files.
- Accelerator config is intentionally env-driven so applications can use new package config keys without publishing config files in every project.
- Deploy orchestration config lives in `.env.envoy`, not Laravel runtime config.
- Accelerator deploys default to two stages: `test` and `prod`; the default stage is `test`.
- `AddLinkHeadersForPreloadedAssets` is wrapped by `ConditionalLinkPreload`. Skips `admin` / `admin/*` paths by default. Toggle via `accelerator.middleware.link_preload.enabled`.

### Installation

- Use `php artisan accelerator:install` for interactive installs.
- For AI agents or non-TTY terminals, use explicit flags such as `--no-interaction`, `--preset=none`, `--component=*`, `--with-deploy`, `--with-pwa`, `--with-boost`.
- Discovery: `php artisan accelerator:install --list-components` prints the wizard component table.
- Verification (read-only): `php artisan accelerator:install --check` delegates to `agent:audit`.
- Skip migrate finalisation: `--no-migrate`.
- Post-install hooks: `--with-shield` (auto-on when `filament-core` or `app-config` is selected), `--with-pint` (auto-on when binary exists). `--without-shield` / `--without-pint` to opt out.
- `--without=frontend-core` when a project already owns its Inertia frontend.
- `--stage-mode=single --default-stage=prod` for production-only projects.
- Configs are published per-component (only configs declared by selected components — installing only `reverb` publishes only `broadcasting.php` + `reverb.php`).
- `.env.envoy`, `.env.staging`, `.env.production` are local-only, gitignored. Sensitive credentials are blanked when seed files are generated; operator fills before scp.

### Deployment

- Use Envoy as the deployment orchestrator. Operator writes Nginx vhost + Supervisor config manually once per VPS — Envoy does NOT generate those files.
- Do not bootstrap Laravel config from Envoy.
- Envoy reads deploy configuration from project-root `.env.envoy`, which contains only `OPS_DEPLOY_*` keys and must not be committed.
- Per-stage `OPS_DEPLOY_{STAGE}_OCTANE_PORT` is REQUIRED — Envoy `health-check` curls Octane directly.
- Do not put `OPS_DEPLOY_*` keys in `.env`, `.env.staging`, `.env.production`, `.env.example`, or `.base-env.example`.
- Envoy syncs the selected runtime env seed to `{root}/shared/.env` on every deploy; old shared env is archived first.
- Use the package Envoy bridge at `vendor/wireninja/accelerator/resources/envoy/Envoy.blade.php`; project Envoy files only define server aliases.
- `vendor/bin/envoy run init --stage=test` for the first deploy (skips db-backup, maintenance, prune by design).
- `vendor/bin/envoy run deploy --stage=test` for continuous releases.
- `vendor/bin/envoy run deploy-slim --stage=test` for backend hot-patch (no JS/CSS rebuild).
- `vendor/bin/envoy run rollback --stage=test` switches `current` back to the latest valid release (validated for `vendor/autoload.php` + `.env` symlink). No maintenance window during rollback — Octane restart is fast.
- `vendor/bin/envoy run releases --stage=test` prints the release history and prune target.

### Continuous Deploy Flow

1. Sandbox: `ensure-deploy-tools` → `sync-env` → `clone-release` (`--depth=1`) → `link-shared` → `build-release` → `harden-release` (excludes vendor).
2. Risky zone: `db-backup` (Spatie `--config=backup_predeploy`) → `maintenance-on` (random secret printed once for operator preview) → `prepare-laravel` (larahelp + migrate) → `switch-current` → `invalidate-opcache` → `restart-service` (sleep + grep FATAL fail-fast) → `health-check` (curl Octane `/up`).
3. Cleanup: `maintenance-off` → `prune-releases` (keep `OPS_DEPLOY_KEEP_RELEASES`, preserve current).

If health-check fails, the app stays in maintenance mode for operator triage. Better than exposing a broken release.

### Release Layout

- ISO-like release folders under `{root}/releases`.
- `{root}/current` points to the active release.
- `{root}/shared/.env` is the runtime env source.
- `{root}/shared/storage` is shared between releases.
- `{root}/shared/database` for shared SQLite (when used).
- Public storage links to `{root}/shared/storage/app/public`.
- Do not serve Laravel from a legacy `{root}/html/public` after migration to release layout.
- `{root}/archive` retains rollback evidence and pre-replace snapshots; `prune-releases` does NOT touch it.

### Server Safety

- Scope production operations to the configured stage/domain/root.
- Do not touch unrelated domains or projects from a deployment command.
- Archive replaced Nginx and Supervisor files before overwriting them (operator-side responsibility — Envoy doesn't write those).
- Keep `{root}/archive` while a deployment is being proven; it contains rollback evidence and archived legacy paths.
- Do not delete deploy archives blindly. Prune archives only after the active release, rollback path, Nginx config, and Supervisor services are verified.
- For SSH cleanup during migration, prefer moving legacy paths into `{root}/archive` instead of deleting them.

### Nginx, Supervisor, And Runtime

- Supervisor program names must be stage-scoped (e.g. `wss_test_octane`) to avoid cross-project collisions.
- Nginx config should point to `{root}/current/public`.
- Validate Nginx with `nginx -t` before reload.
- Invalidate OPcache per PHP file in the new release before restarting services; do not use global `opcache_reset()` as a deploy default.
- Nightwatch is opt-in and must have explicit host/port config.
- Trust proxy behavior belongs in Accelerator built-in middleware (`accelerator.proxy.trust_local`), not duplicated in user-land application bootstrap.

### Pre-Deploy Backup & Restore

- Spatie profile `backup_predeploy` is published to `config/backup_predeploy.php` via the `app-config` install component.
- Folder/name namespaced to `{APP_NAME}-predeploy` so retention does NOT collide with the scheduled backup pool.
- Aggressive retention: 2 days keep_all, no weekly/monthly/yearly, 1000MB max.
- Notifications disabled (deploy-driven, would otherwise spam).
- Restore is manual by design. Locate the latest zip, extract, feed dump to native db client.

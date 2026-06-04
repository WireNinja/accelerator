---
name: accelerator-breaking-changes
description: Breaking changes and required userland actions when upgrading wireninja/accelerator. Also lists non-breaking improvements per version for AI agent awareness.
---

# Accelerator Breaking Changes

Format per entry:
- **🔴 BREAKING** = Userland must take action or deploy will fail.
- **🟡 AWARENESS** = No action required, but behavior changed. AI agents should know.

---

## v1.1.77

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Activity audit relation now respects configured field order and visibility

`ActivitiesRelationManager` now filters and orders displayed change rows using `config/audit.php` attributes plus configured relationship snapshot keys. Removing an attribute such as `manager_id` from the model audit config hides it from old and new activity detail views instead of falling back to generated English-ish labels.

**Action required**: None. If Octane workers have cached old audit config, restart the worker or call `WireNinja\Accelerator\Support\ActivityLog\AuditConfig::flush()` in the current process before expecting changed config labels/order to appear.

### 🟡 AWARENESS — Activity detail separates technical event, description, time, and actor

Activity detail now shows the technical event as a localized event label, treats duplicate event/description values as missing custom description, adds relative time next to the exact timestamp, and renders the actor as a clean headerless card with name, username, email, avatar, and role badges when available.

**Action required**: None. Custom activity descriptions remain supported and are still shown as the activity keterangan.

---

## v1.1.76

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Profile page stays simple for tenant-enabled panels

Accelerator now registers `ManageProfile` with Filament's simple profile layout. This keeps `/admin/profile` tenantless and prevents tenant-enabled panels from rendering tenant-scoped navigation while no `{tenant}` route parameter exists.

**Action required**: If an application previously expected the user profile page to use the full panel shell, keep that customization in userland. Tenant-enabled panels should not switch the bundled profile page back to `isSimple: false` unless the profile route is made tenant-aware.

### 🟡 AWARENESS — Profile form can show an optional Organization step

`ManageProfile` now detects an active Filament tenant or a user relationship named `organization()` / `organizations()` and adds an `Organisasi` wizard step for editing the linked organization's `name`. Accelerator remains single-tenant by default; projects still opt into Filament tenancy from their own panel provider.

**Action required**: None for single-tenant applications. Multi-tenant applications that want this bundled profile step should expose an `organization()` or `organizations()` relationship on the authenticated user model, or rely on Filament's current tenant when visiting the profile inside a tenant context.

### 🟡 AWARENESS — Ticketing page now participates in Shield page permissions

`TicketingPage` now uses Filament Shield's page trait so its visibility and access can be governed by generated page permissions.

**Action required**: Projects using Shield should regenerate or review permissions after upgrading if `TicketingPage` is enabled in the panel.

---

## v1.1.75

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — v1.1.74 tenantless-route note narrowed to sidebar only

Patch `v1.1.74` only guards Accelerator's custom sidebar tenant menu. It does not override Filament's native topbar, profile page layout, or any userland topbar behavior. Do not introduce a custom topbar component to solve tenantless profile routes unless the exact compiled view proves the topbar is the failing renderer.

**Action required**: If `getTenantName(null)` still appears on `/admin/profile`, inspect the application's compiled Blade stack and tenant/profile panel configuration before changing Accelerator UI surfaces. The Accelerator sidebar no longer calls `getTenantName(null)`.

---

## v1.1.74

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Sidebar tenant menu is hidden on tenantless routes

The Accelerator sidebar now renders Filament's native tenant menu only when the current request has a concrete tenant model. Filament's tenant menu component calls `getTenantName()` with the current tenant immediately during render, so the sidebar must not render it while `filament()->getTenant()` is `null`.

**Action required**: Upgrade if a tenant-enabled panel hits `Filament\FilamentManager::getTenantName(): Argument #1 ($tenant) must be of type Illuminate\Database\Eloquent\Model, null given` from the Accelerator sidebar on tenantless routes.

---

## v1.1.73

No deployment contract changes beyond v1.1.66.

### 🔴 BREAKING — WebPush dependency upgraded to v11

`laravel-notification-channels/webpush` is now constrained to `11.0`. This drops Laravel 11 support, casts `PushSubscription::$content_encoding` to `Minishlink\WebPush\ContentEncoding`, makes `MessageValidationFailed` final, and requires custom `WebPushMessageInterface` implementations to provide `getOptions()`.

**Action required**: Before upgrading, check custom WebPush notifications, custom `PushSubscription` handling, classes extending `MessageValidationFailed`, and custom `WebPushMessageInterface` implementations. Applications only using Accelerator's default push subscription model, migration, and `HasPushSubscriptions` trait do not need a database migration.

### 🟡 AWARENESS — Accelerator sidebar tenant menu now follows Filament v5 API

The custom Accelerator sidebar no longer calls the removed `filament()->getTenantMenuPosition()` API. It now renders Filament's native `<x-filament-panels::tenant-menu />` whenever `filament()->hasTenancy()` and `filament()->hasTenantMenu()` are true, keeping Filament's tenant switcher, searchable tenant list, menu items, and render hooks intact while applying Accelerator sidebar styling.

**Action required**: Projects that hit `Call to undefined method Filament\FilamentManager::getTenantMenuPosition()` on tenant-enabled panels should upgrade to this patch and rebuild frontend assets if the sidebar styling is not reflected.

---

## v1.1.72

No deployment contract changes beyond v1.1.66.

### 🔴 BREAKING — Sidebar toggle view replaced by topbar view

The old `accelerator::filament.sidebar.toggle` view has been removed and the panel preset now renders `accelerator::filament.sidebar.topbar` at `PanelsRenderHook::PAGE_START`.

**Action required**: If an application references or overrides `accelerator::filament.sidebar.toggle`, move that customization to `accelerator::filament.sidebar.topbar` before upgrading.

### 🔴 BREAKING — Sidebar user role lookup now requires `getRoleNames()`

The Accelerator sidebar now calls `$user->getRoleNames()->first()` directly when building the sidebar user description. The previous defensive callable/iterable guards were removed because Accelerator's configured user contract expects Spatie role support.

**Action required**: Custom authenticated user models used with the Accelerator sidebar must expose Spatie Permission's `getRoleNames()` collection API, typically by using `Spatie\Permission\Traits\HasRoles` or extending `WireNinja\Accelerator\Model\AcceleratedUser`.

### 🟡 AWARENESS — Sidebar, topbar, wizard, and user menu visual refresh

The bundled Filament sidebar markup was reorganized for a framed sidebar shell, topbar collapse trigger, parent-panel divider, footer divider, full-width user menu, adjusted page header spacing, and lighter vertical wizard canvas styling. This is an internal UI refresh, but projects with published or heavily customized Accelerator views should compare their overrides.

### 🟡 AWARENESS — Timestamp summary table columns added

New reusable Filament table columns are available: `TimestampSummaryColumn`, `CreatedAtColumn`, `UpdatedAtColumn`, and `DeletedAtColumn`. They render relative time plus translated date/time through the new `accelerator::filament.tables.columns.timestamp-summary-column` view. They are additive and do not replace existing columns automatically.

### 🟡 AWARENESS — Global Filament action defaults changed

Accelerator now sets a localized filter apply action label/icon and default icons for global create, edit, and delete actions. Existing per-resource action configuration still wins when explicitly set.

### 🟡 AWARENESS — Users without uploaded avatars get DiceBear fallback

`AcceleratedUser::getFilamentAvatarUrl()` now returns a generated DiceBear avatar URL when the user has no stored avatar. Projects that intentionally relied on a null avatar URL should override the method.

### 🟡 AWARENESS — Starter system enum stubs updated

Fresh Accelerator installs now publish `LauncherEnum`, add the `System` panel to `PanelEnum`, move support resource namespaces to `WireNinja\Accelerator\Filament\Resources\Support\...`, use enum values as resource class strings, and group resources by `PanelEnum` values. Existing application enums are not changed unless stubs are republished or overwritten.

---

## v1.1.71

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Fresh-seed removes stale dev-only provider manifests

Fresh-seed releases are optimized while dev dependencies are installed for seeding. An auto-discovered dev-only package such as `laravel/boost` can therefore be recorded in Laravel's bootstrap caches. Once the locked production install removes dev dependencies, those caches must not be booted. `prune-dev-dependencies` now deletes bootstrap PHP cache files after `composer install --no-dev --no-scripts` and before production re-optimization.

**Action required**: Upgrade before using `deploy-fresh-seed` on applications with auto-discovered dev-only packages. A deployment that failed after seeding remains in maintenance mode until a corrected deployment completes or the operator explicitly restores service.

---

## v1.1.70

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Fresh seed preserves the configured runtime environment

The confirmed `deploy-fresh-seed` one-shot process now calls Laravel's `DB::prohibitDestructiveCommands(false)` before `migrate:fresh --seed --force`. This enables its nested `db:wipe` command while retaining the stage's real `APP_ENV` and runtime env selection.

Patch `v1.1.69` used a temporary `APP_ENV=local` override. It did not edit `.env`, but Laravel may use an exported `APP_ENV` to select an existing `.env.local` file and it can change environment-sensitive boot/seeder behavior. Upgrade before running fresh-seed.

### 🟡 AWARENESS — PHP-FPM root requests reach Laravel

Generated PHP-FPM Nginx locations now set `index index.php`, preventing a request for `/` from resolving as a forbidden public directory while `/up` still appears healthy.

**Action required**: FPM deployments affected by root-page 403 must upgrade and re-run `vendor/bin/envoy run bootstrap --stage={stage}`.

---

## v1.1.69

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Confirmed fresh-seed uses scoped environment override and locked dependencies

`deploy-fresh-seed` now runs re-optimization and `migrate:fresh --seed --force` inside a temporary `APP_ENV=local` override that is removed when that command scope exits. This avoids production destructive-command guards blocking the internal `db:wipe` operation while keeping ordinary production commands protected.

All Envoy Composer install phases now require `composer.lock` and continue to use `composer install`, never dependency resolution via `composer update`. A deploy therefore installs the exact versions reviewed during development instead of accepting newly resolved dependency versions on the server, reducing supply-chain exposure.

**Action required**: Keep `composer.lock` committed. For fresh-seed, upgrade to v1.1.70 or later before retrying in any stage.

---

## v1.1.68

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Backup listing handles application names with spaces

The Envoy `backups` story now parses backup paths without splitting an application name such as `Supervisi Akademik` into invalid size fields. This fixes `unbound variable` failures while listing predeploy or scheduled backup files.

**Action required**: None. Upgrade before using `vendor/bin/envoy run backups` for applications whose `APP_NAME` contains spaces.

---

## v1.1.67

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Nginx bootstrap preserves existing SSL vhosts

`bootstrap-nginx` and `bootstrap-ssl` now inspect Let's Encrypt certificates through `sudo` and use a boolean SSL-config guard. This prevents a configured HTTPS vhost from being replaced with the HTTP-only template merely because the deploy user cannot stat root-owned certificate links.

**Action required**: Upgrade to this patch before re-running `bootstrap` on an HTTPS stage.

---

## v1.1.66

### 🔴 BREAKING — Deployment runtime and managed services are now explicit

New generated deploy env files default to PHP-FPM and no Supervisor-managed application services. Existing Octane deployments must declare their runtime and enabled programs before the next `bootstrap`:

```dotenv
OPS_DEPLOY_{STAGE}_HTTP_RUNTIME=octane
OPS_DEPLOY_{STAGE}_OCTANE_WORKERS=1
OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS=0
OPS_DEPLOY_{STAGE}_HORIZON_ENABLED=true
OPS_DEPLOY_{STAGE}_REVERB_ENABLED=true
OPS_DEPLOY_{STAGE}_SCHEDULER_ENABLED=true
OPS_DEPLOY_{STAGE}_NIGHTWATCH_ENABLED=true
```

`OPS_DEPLOY_{STAGE}_HTTP_RUNTIME=fpm` instead renders PHP-FPM Nginx locations using `OPS_DEPLOY_{STAGE}_FPM_SOCKET` and does not start Octane. Reverb websocket Nginx configuration is emitted only when Reverb is enabled. Nightwatch now actually renders `nightwatch:agent` when enabled.

For Swoole, Envoy renders `octane:swoole` so `0` genuinely disables task workers; Laravel Octane's public dispatcher otherwise converts `--task-workers=0` back to its `auto` fallback.

Apps using Redis queue without Horizon may set:

```dotenv
OPS_DEPLOY_{STAGE}_HORIZON_ENABLED=false
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_ENABLED=true
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_CONNECTION=redis
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_QUEUE=default
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_PROCESSES=1
```

**Action required**: Before re-running `vendor/bin/envoy run bootstrap --stage={stage}`, set `_HTTP_RUNTIME` and explicit service flags in `.env.envoy`. Do not enable Horizon and `QUEUE_WORKER_ENABLED` together. When Nightwatch is enabled, set runtime `NIGHTWATCH_ENABLED=true` and keep `NIGHTWATCH_INGEST_URI` aligned with `_NIGHTWATCH_PORT`.

### 🟡 AWARENESS — Deploy env has a package-owned base template

The package now ships `.base-env.envoy.example`, and `accelerator:install --with-deploy` renders project `.env.envoy` from it. Existing projects can retain their manually configured deploy env; no overwrite occurs without `--force`.

The base runtime example now defaults to `NIGHTWATCH_ENABLED=false` with `LOG_STACK=daily`. Projects enabling Nightwatch must opt into both the collector and `daily,nightwatch` logging stack.

---

## v1.1.65

No breaking changes.

### 🟡 AWARENESS — Fresh-seed bootstrap loads Composer autoload

The confirmed `deploy-fresh-seed` production bypass now loads `vendor/autoload.php` before bootstrapping Laravel in the one-off PHP process. This fixes the v1.1.64 failure where `bootstrap/app.php` could not resolve `Illuminate\Foundation\Application`.

**Action required**: None. Use the same explicit confirmation flag.

---

## v1.1.64

No breaking changes.

### 🟡 AWARENESS — Fresh-seed deploy works in production

`deploy-fresh-seed` now explicitly unprohibits Laravel's `migrate:fresh` command inside the already-confirmed Envoy flow before running:

```bash
php artisan migrate:fresh --seed --force
```

This is required because Accelerator globally calls `DB::prohibitDestructiveCommands(app()->isProduction())`. The bypass is scoped to the one-off console kernel call inside `prepare-laravel-fresh-seed`; normal production commands remain protected.

**Action required**: None. Use the same explicit confirmation flag as v1.1.63.

---

## v1.1.63

No breaking changes.

### 🟡 AWARENESS — Destructive fresh-seed deploy story

New Envoy story:
```bash
vendor/bin/envoy run deploy-fresh-seed --stage=test --i-understand-this-will-drop-and-reseed-database="aku mengkonfirmasi remigrate fresh seed"
```

This story is intentionally destructive. It runs the normal release build path with Composer dev dependencies available, enters maintenance mode, runs `php artisan migrate:fresh --seed --force`, then prunes dev dependencies with the production Composer install before switching `current`.

Use it only when the operator explicitly wants a fresh database and fresh seeded data. Existing database data is destroyed after the pre-deploy backup.

**Action required**: None. Additive feature.

---

## v1.1.60

### 🔴 BREAKING — Nginx stub renamed and rewritten

Old stub `nginx-vhost.conf.stub` no longer exists. Replaced by two stubs:
- `nginx-vhost-http.conf.stub` — HTTP-only, used when no SSL cert exists
- `nginx-vhost-ssl.conf.stub` — Full SSL + HTTP/2 + HTTP/3 (QUIC)

Both stubs now use the `@octane` named location pattern (`try_files $uri @octane`) instead of the old `upstream` block + direct `proxy_pass`.

**Action required**: Re-run `vendor/bin/envoy run bootstrap --stage={stage}` to regenerate Nginx config. Bootstrap now auto-detects SSL cert and uses the correct stub.

### 🔴 BREAKING — `bootstrap-nginx` now guards against SSL downgrade

If existing Nginx config has `ssl_certificate` but no cert file is found at `/etc/letsencrypt/live/{domain}/`, bootstrap will **skip** instead of overwriting. This prevents accidental SSL → HTTP downgrade.

**Action required**: None if certs are in place. If bootstrap skips unexpectedly, verify the certificate path and restore the archived vhost before retrying.

### 🟡 AWARENESS — New `bootstrap-ssl` story

New story to obtain SSL cert and upgrade Nginx config in one command:
```bash
vendor/bin/envoy run bootstrap-ssl --stage=test
```

Uses `certbot certonly --webroot` (does NOT use `certbot --nginx` plugin which conflicts with QUIC). Then renders the SSL stub and reloads nginx.

**Action required**: None. Additive feature. Replaces manual certbot + manual nginx edit.

---

## v1.1.59

No breaking changes. Added this skill file.

---

## v1.1.58

### 🔴 BREAKING — `larahelp` v2.0

Old `larahelp` v1 on VPS is incompatible with the new release layout expectations. The new version follows symlinks, auto-detects SQLite, and respects env vars.

**Action**: Update the binary on every VPS:
```bash
scp vendor/wireninja/accelerator/stubs/vps/larahelp onidel:/tmp/larahelp
ssh onidel 'sudo mv /tmp/larahelp /usr/local/bin/larahelp && sudo chmod 755 /usr/local/bin/larahelp'
```

### 🟡 AWARENESS — `prepare-layout` enhanced

Now auto-creates `storage/framework/{views,cache,sessions}`, sets ownership, applies ACL, creates SQLite file. No action needed — backward-compatible.

---

## v1.1.57

### 🟡 AWARENESS — `clear-cache` task added to deploy flow

`optimize:clear` now runs on `current` before `db-backup`. Prevents stale config cache from breaking backup commands. No action needed.

### 🟡 AWARENESS — `backups` story added

New story `vendor/bin/envoy run backups --stage=test` lists predeploy and scheduled backup files. No action needed.

---

## v1.1.56

### 🔴 BREAKING — `OPS_DEPLOY_BUN_BIN` renamed to `OPS_DEPLOY_NPM_BIN`

Deploy will fail if the old key is still used.

**Action**: Rename in `.env.envoy`:
```diff
- OPS_DEPLOY_BUN_BIN=/path/to/bun
+ OPS_DEPLOY_NPM_BIN=pnpm
```

Per-stage override also renamed: `OPS_DEPLOY_{STAGE}_BUN_BIN` → `OPS_DEPLOY_{STAGE}_NPM_BIN`.

If the key is empty, auto-detects: pnpm → bun → npm.

### 🔴 BREAKING — `accelerator:install --bun-bin` renamed to `--npm-bin`

**Action**: Update any CI scripts or automation using `--bun-bin` to `--npm-bin`.

### 🟡 AWARENESS — Blade compiler collision fixed in `renderStub`

`vendor/bin/envoy run bootstrap` now correctly renders stub placeholders. Previously stubs were uploaded raw. No action needed unless you have manual workarounds to remove.

### 🟡 AWARENESS — Conditional `build-release` per package manager

`build-release` now branches based on configured NPM_BIN value:
- `pnpm` → `pnpm install --frozen-lockfile`
- `bun` → `bun install --frozen-lockfile`
- `npm` → `npm ci --no-audit --no-fund`

No action needed — handled automatically by the renamed key.

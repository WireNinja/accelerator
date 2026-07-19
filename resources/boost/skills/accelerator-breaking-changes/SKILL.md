---
name: accelerator-breaking-changes
description: Breaking changes and required userland actions when upgrading wireninja/accelerator. Also lists non-breaking improvements per version for AI agent awareness.
---

# Accelerator Breaking Changes

Format per entry:
- **🔴 BREAKING** = Userland must take action or deploy will fail.
- **🟡 AWARENESS** = No action required, but behavior changed. AI agents should know.

---

## v2.0.0

### 🔴 BREAKING — Telemetry v1 is replaced, not migrated in place

`TelemetryManager`, `TelemetryDatabase`, `TelemetryMigration`, the static recorder state, query timeline, log reader, source capture, and the v1 dashboard components are removed. `TelemetryBuffer` now defines both Octane tables and `TelemetryStore` owns schema `200`.

Stop Octane and archive `storage/telemetry/telemetry.sqlite` plus any `-wal` / `-shm` files under `.accelerator_v1/telemetry/` before enabling v2. Accelerator refuses an unknown/v1 schema and never drops it automatically. Remove `ACCELERATOR_TELEMETRY_CAPTURE_GUESTS` and `ACCELERATOR_TELEMETRY_THROTTLE`; add explicit `CAPTURE_HEADERS`, `CAPTURE_QUERY`, `CAPTURE_PAYLOAD`, and `NOTIFICATION_RETRY` values. Restart Octane so both new tables are allocated.

Telemetry now captures authenticated requests only. Persistence is idempotent by buffer ID, Swoole rows are acknowledged only after SQLite commit, and channel-specific notifications use a durable post-commit outbox. The supported claim is “no request-path disk I/O”, not “zero latency”.

### 🔴 BREAKING — Fake contracts and thin Filament wrappers removed

`HasHandle`, `PanelColor`, `Profile`, `TypeCaster`, `BetterActionGroup`, `AuditRelationGroup`, `AcceleratorPanelsRenderHook`, widget lazy/polling traits, and all timestamp column wrappers no longer exist. Use concrete action classes, literal Filament color names, env-backed support config, `Cast`, native `ActionGroup`/`RelationGroup`, native render-hook strings, native widget properties, and `TextColumn::since()` with `description()` or a date tooltip.

### 🔴 BREAKING — Support identity is application configuration

Set `ACCELERATOR_SUPPORT_WHATSAPP` and `ACCELERATOR_SUPPORT_TELEGRAM` when bundled support actions should be visible. Accelerator no longer ships a developer's personal contact constants.

### 🔴 BREAKING — Self-registration removed

The database-backed `registration_enabled` toggle and Filament registration route are removed. Accelerator v2 targets internal provisioned-user applications; a project that genuinely needs public signup must configure that application-owned flow explicitly.

### 🟡 AWARENESS — Filament uses one Vite theme input

`PanelPreset` now requests only the resolved application theme. Accelerator CSS is imported by that theme and is no longer requested as an impossible second vendor manifest entry.

### 🔴 BREAKING — Runtime typed-column magic removed

`HasTypedColumnMethods` and its generated `getColumn*()` / `setColumn*()` API are removed. Read casted Eloquent attributes directly and write them with property assignment, `fill()`, or `update()`. Runtime behavior and static typing must not depend on generated PHPDoc.

### 🔴 BREAKING — The application owns its user model

`AcceleratedUser` and `AcceleratedUserService` are removed. `App\Models\User` must extend Laravel's `Authenticatable`, implement `WireNinja\Accelerator\Contracts\AcceleratorUser`, and explicitly own its Filament MFA, role, avatar, suspension, notification, and impersonation behavior. Package relationships resolve the configured `auth.providers.users.model`; they no longer point to a package base model.

### 🔴 BREAKING — OAuth no longer stores provider tokens

Google OAuth is disabled unless its feature and mode are enabled. `existing_only` authenticates only a pre-provisioned matching email. `allowed_domains` is the explicit provisioning mode and requires a configured domain allowlist. Neither mode stores Google access or refresh tokens; the v2 baseline removes those columns.

### 🔴 BREAKING — Application god-trait removed

`InteractsWithApplication` is removed. Applications must not import package provider internals. Core owns Eloquent/session/password defaults; the Filament provider owns UI and Shield defaults. Telegram settings are applied lazily when the Telegram client is resolved, so application boot no longer queries settings or silently swallows that failure.

### 🔴 BREAKING — BetterEnum is comparison-only

`BetterEnum` retains `is`, `isNot`, `isAny`, `isNone`, `resolve`, and the default gray `getColor`. Unused collection, array, presentation, name-resolution, and option aliases are removed. Pass the enum class directly to Filament `options()` and use native `cases()`, `getLabel()`, `getDescription()`, `getIcon()`, and `getColor()` APIs.

### 🔴 BREAKING — Authentication routes are no longer database settings

`system.password_reset_enabled` and `system.email_verification_enabled` are removed. Filament password reset, email verification, and email-change verification routes are registered during panel configuration for every Accelerator panel. They cannot be enabled or disabled from `SystemSettings`, because route registration is complete before Filament runs `bootUsing()`. Existing settings rows are deleted by the v2 settings migration. Public self-registration remains disabled.

`App\Models\User::canAccessPanel()` must not reject an unverified user. Return false for suspended/unauthorized users, but let Filament's email-verification middleware own the verified-email gate; otherwise the verification prompt and link are registered but unreachable.

### 🟡 AWARENESS — Local log mail is observable again

Fresh local `.env` / `.env.example` now use `LOG_LEVEL=debug`, while generated staging and production seeds force `LOG_LEVEL=error`. This makes the default local `MAIL_MAILER=log` useful for password-reset and verification links. Those notifications are queued by Filament, so run the generated queue worker before reading the mail log.

### 🔴 BREAKING — Schedule wrappers and dead system widget removed

`BuiltinSystemSchedule` and the unused `SystemInfoWidget` are removed. Define schedules directly with Laravel's `Schedule` facade. Fresh installs always schedule database/file backups and only render ticketing, Horizon, or telemetry schedules when onboarding activates that feature.

### 🔴 BREAKING — Resource context is ResourceEnum-backed

`agent:resource-context` now treats the configured `ResourceEnum` as the authoritative registry and removes the duplicate filesystem discovery layer, arbitrary complexity score, fake empty form/table/page/widget registries, and two synthetic Filament host classes. `--expand` is now real and adds detailed trees/source data. Managed resources need `#[DiscoverAsResource]`; registered external vendor resources are reported explicitly and are exempt from Accelerator-owned metadata checks.

### 🔴 BREAKING — Duplicate model commands removed

`accelerator:generate-model-outline`, `accelerator:model-audit`, and `accelerator:model-doc` are removed. Use Laravel's native `model:show {Model} --json` for the standard overview and `agent:model-context {Model} --compact` for Accelerator schema-key diagnostics. Calling `agent:model-context` without a model now lists the application model registry; use `--all` for the intentionally large full scan and `--expand` for detailed schema and relation keys. The Boost skill is renamed from `accelerator-model-outline` to `accelerator-model-context`.

### 🔴 BREAKING — Lookup query wrapper removed

`Support\Filament\Lookup` is removed. It wrapped one `Get` value and one Eloquent `whereKey()->pluck()` chain, had one application caller, and silently returned an empty array when misconfigured. Write the dependent options query directly with `Arr::wrap($get(...))`.

`Support\Cast::asBool()` is also removed after its final internal consumer disappeared. Keep `asString()`, `strictString()`, and `asInt()` only at genuinely mixed input boundaries.

### 🔴 BREAKING — Ceremonial Filament plugins, count badges, and launcher panel removed

`BuiltinSettingPlugin` and `BuiltinTicketingPlugin` are removed. Accelerator now registers their pages/resources directly in the owning System and Support panel providers, while ticket policies are registered by `TicketingServiceProvider`.

`AutoBadge` is removed. It performed hidden cached full-model counts for Ticket and Ticket Board navigation without representing actionable state. Define an application-specific navigation badge explicitly when a meaningful scoped count exists.

`AppLauncherWidget` is removed. External application launchers already render in the shared sidebar, so a second panel showing the same links was redundant. Remove application `AppPanelProvider` classes and their `PanelEnum::App` cases.

Fresh installs no longer create `LauncherEnum`. It is optional, application-owned, and discovered when the class exists. Fresh `PanelEnum` contains only Admin, Support, and System; add application domain panels explicitly. The sidebar filters enum cases against Filament's registered panels, preventing inactive cases from producing dead links.

### 🔴 BREAKING — Choice-card copies replaced by upstream components

`AdvancedCheckboxCards` is removed together with its copied 197-line view. Use `CodeWithDennis\FilamentAdvancedChoice\Filament\Forms\Components\CheckboxCard` directly.

`AdvancedRadioCards` is renamed to `IconRadioCard`. It extends the maintained upstream `RadioCard` and owns only Accelerator's enum-icon view; searchable options, grid behavior, colors, extras, and hidden-input behavior stay upstream-owned.

`TimestampSummaryColumn` is also removed. Its only application consumer had two columns in one table, while native `TextColumn::since()` plus `description()` preserves the same relative and exact timestamp UX without a package class or Blade view.

### 🟡 AWARENESS — Render hooks are registered by their feature owners

`PanelPreset` no longer registers empty OAuth, PWA, or settings hook closures on every panel. `OAuthServiceProvider` owns the Google login hook and only exposes it when both client ID and secret are configured. `PwaServiceProvider` owns the PWA head hook. `FilamentServiceProvider` owns the shared topbar/error hooks and conditionally owns settings notice/support hooks. Disabled features now register no corresponding hook.

## v1.1.79

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Sidebar support actions now follow support settings

Accelerator now registers WhatsApp and Telegram support user-menu actions during panel boot after `SystemSettings` is resolved. The actions are only visible when `support_enabled` is active and the matching support profile value is configured.

**Action required**: None. Projects that disable bundled support should no longer see empty support actions in the user menu after upgrading.

### 🟡 AWARENESS — Sidebar topbar hook is mobile-only

The custom sidebar topbar hook now renders only below the small breakpoint, and page header spacing is tightened to better align with that mobile-only header.

**Action required**: None. Rebuild frontend assets if the updated Accelerator CSS is not reflected.

## v1.1.78

Deployment contract changes from v1.1.66 are listed below.

### 🔴 BREAKING — Envoy deploy binary and Octane server keys renamed

The deploy template now uses clearer `.env.envoy` keys:

```diff
- OPS_DEPLOY_NPM_BIN=pnpm
+ OPS_DEPLOY_PACKAGE_MANAGER_BIN=pnpm

- OPS_DEPLOY_{STAGE}_RUNTIME=swoole
+ OPS_DEPLOY_{STAGE}_OCTANE_SERVER=swoole
```

`OPS_DEPLOY_PHP_BIN`, `OPS_DEPLOY_PACKAGE_MANAGER_BIN`, and `OPS_DEPLOY_{STAGE}_FPM_SOCKET` may now be left empty. Envoy resolves PHP on the VPS in this order: `php8.5`, `php8.4`, `php8.3`, `php`. The package manager resolves in this order: `pnpm`, `bun`, `npm`. FPM socket auto-detection tries the selected PHP version first, then common PHP 8.5 / 8.4 / 8.3 socket paths.

**Action required**: Rename the keys in every project `.env.envoy` before republishing the deploy template. Remove stale `OPS_DEPLOY_SERVER`; it was never read by the package Envoy bridge. Existing `OPS_DEPLOY_SSH_HOST` remains required for local `ssh` / `scp` bootstrap tasks and must match the project `@servers(['vps' => ...])` alias.

### 🔴 BREAKING — Envoy PHP-FPM config now uses PHP version + optional pool intent

FPM deployments should now express intent with PHP version and optional FPM pool instead of manually repeating the same PHP version in the CLI binary, FPM socket, and service unit.

```diff
+ OPS_DEPLOY_PHP_VERSION=8.5
  OPS_DEPLOY_PHP_BIN=

+ OPS_DEPLOY_{STAGE}_FPM_POOL=
  OPS_DEPLOY_{STAGE}_FPM_SOCKET=
+ OPS_DEPLOY_{STAGE}_FPM_SERVICE=
```

Dedicated client pools can be configured without repeating the socket/service path:

```dotenv
OPS_DEPLOY_PHP_VERSION=8.4
OPS_DEPLOY_TEST_FPM_POOL=wahyudi
OPS_DEPLOY_TEST_FPM_SOCKET=
OPS_DEPLOY_TEST_FPM_SERVICE=
```

Envoy derives:

- PHP CLI binary: `php8.4`
- preferred dedicated socket: `/run/php/php8.4-fpm-wahyudi.sock`
- preferred dedicated service: `php8.4-fpm-wahyudi.service` when the unit exists on the VPS, otherwise `php8.4-fpm.service`

The old explicit keys remain supported as advanced overrides. Fill `OPS_DEPLOY_PHP_BIN`, `OPS_DEPLOY_{STAGE}_FPM_SOCKET`, or `OPS_DEPLOY_{STAGE}_FPM_SERVICE` only when the VPS uses non-standard names.

**Action required**: Update project `.env.envoy` files to add `OPS_DEPLOY_PHP_VERSION` and `OPS_DEPLOY_{STAGE}_FPM_POOL`. Prefer blank socket/service overrides unless the server uses a custom path or unit. For dedicated FPM masters, make sure the derived service exists before deploy; Envoy reloads the resolved FPM service during `restart-service`.

### 🟡 AWARENESS — Isolated `/srv/clients/{client}` ownership is still explicit operator policy

This patch does not change deploy root ownership behavior. `prepare-layout` still assumes the SSH deploy user can create and own the configured root while ACLs grant the runtime user access to writable paths.

**Action required**: For strict client isolation such as `/srv/clients/wahyudi`, decide the deploy user, runtime user, root owner, and group policy before changing `prepare-layout`. Do not blindly switch to `sudo su`; use passwordless `sudo` per privileged command.

### 🟡 AWARENESS — Accelerator ops skills now have stricter boundaries

`accelerator-env-config`, `accelerator-deployment`, and `accelerator-ops-observability` now document clearer ownership:

- `accelerator-env-config` owns env/config key contracts and safe redacted env inspection.
- `accelerator-deployment` owns mutating deploy operations such as bootstrap, deploy, rollback, restart, Nginx/Supervisor/systemd changes, and release layout changes.
- `accelerator-ops-observability` is read-only by default and no longer includes Shield regeneration, model audit, or Filament resource verification sections.

**Action required**: None. AI agents should switch skills instead of using observability as a catch-all ops/development checklist.

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

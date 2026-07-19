---
name: accelerator-breaking-changes
description: Required userland actions and removed v1 contracts when upgrading an existing Laravel application to WireNinja Accelerator v2.
---

# Accelerator v2 Breaking Changes

V2 is a clean break. The fresh installer supports pristine Laravel 13 only. Existing applications must migrate surgically; there is no adoption command or compatibility facade.

## Required Before Migration

1. Tag or bundle the last v1 package state.
2. Keep an ignored `.accelerator_v1/` source snapshot for local reference.
3. Back up the application database and v1 telemetry database/WAL/SHM files.
4. Record current routes, panels/resources, schedules, feature env keys, deployment root/domain/group, and active services.
5. Migrate one bounded slice at a time and keep the application bootable between commits.

## Installation And Package Boot

- `php artisan accelerator:install` and its component/preset matrix are removed.
- Fresh install is `bash vendor/wireninja/accelerator/bin/install` after `composer require`.
- The installer is destructive only for a pristine skeleton and runs `migrate:fresh --seed`.
- Existing applications must not run it.
- Bun is the only supported JavaScript package manager; remove `package-lock.json`, `pnpm-lock.yaml`, `yarn.lock`, and `bun.lockb`, retain `bun.lock`, and declare `packageManager=bun@...`.
- Feature packages remain installed; env feature flags control runtime providers/routes.
- Package config is env-driven and is not published merely to copy defaults.

## Application Ownership

- `AcceleratedUser` and `AcceleratedUserService` are removed. `App\Models\User` extends Laravel's authenticator and implements `AcceleratorUser` explicitly.
- `InteractsWithApplication` is removed. Providers own their actual boot responsibilities.
- Public registration is removed. Accelerator v2 is an authenticated internal-app foundation.
- Filament owns ready-made login, reset, verification, email-change verification, and MFA UI.
- Fortify is conditionally registered and headless. Its inactive state means no Fortify routes.
- `canAccessPanel()` must reject suspended/unauthorized users but must not block unverified users before Filament can show its verification flow.
- Initial setup provisions an explicit verified Super Admin. Predictable demo credentials are forbidden outside local opt-in seeding.

## OAuth

- Socialite dependencies remain installed, but OAuth routes require both feature activation and credentials.
- Default mode is `existing_only`: only a matching pre-provisioned user may authenticate.
- `allowed_domains` is the explicit auto-provisioning mode and requires verified Google email plus an allowlist.
- Provider access/refresh tokens are no longer stored. Remove legacy token columns through an explicit application migration.
- OAuth users receive the configured baseline role and suspended users are rejected.

## Removed Fake Or Duplicate APIs

Replace these rather than recreating aliases:

- `HasTypedColumnMethods` and generated `getColumn*` / `setColumn*`: use Eloquent attributes, `fill()`, or `update()`.
- `accelerator:generate-model-outline`, `accelerator:model-audit`, `accelerator:model-doc`: use `model:show` and `agent:model-context`.
- `Lookup`: use the direct Eloquent query.
- `BuiltinSystemSchedule`: define schedules with Laravel's `Schedule` facade.
- `BuiltinSettingPlugin` / `BuiltinTicketingPlugin`: owning panel providers register pages/resources directly.
- `AutoBadge`: calculate an intentional scoped badge where it is consumed.
- timestamp column wrappers: use native `TextColumn::since()` and `description()`.
- duplicated choice cards: use maintained upstream components; only genuinely distinct presentation subclasses remain.
- fake Filament host/resource registries and filesystem discovery: `ResourceEnum` is authoritative.
- `HasHandle`, `PanelColor`, `Profile`, `TypeCaster`, `BetterActionGroup`, `AuditRelationGroup`, render-hook marker classes, and widget polling/lazy traits: use concrete/native APIs.

Do not add temporary package-level compatibility facades. A short-lived adapter may exist in the migrating application only and must have a deletion checkpoint.

## Enum And Resource Changes

- `BetterEnum` is comparison-focused: `is`, `isNot`, `isAny`, `isNone`, `resolve`, and the default color remain. Use native cases and Filament label/icon/color contracts for presentation.
- `ResourceEnum` is the single resource registry.
- Accelerator-managed resources require `#[DiscoverAsResource]`; external registered vendor resources are reported but not forced into package metadata.
- `agent:resource-context --expand` now emits real source/schema details instead of synthetic registries and complexity scores.

## Filament And Frontend

- All panels use one recursively discovered `resources/css/filament/**/theme.css` input unless an application intentionally provides a panel override.
- Remove duplicated panel theme files and hard-coded Vite entries.
- `resources/svg/.gitkeep` is a scaffold invariant and must exist before icon-dependent Composer/application commands.
- Global searchable/preloaded/non-native Select defaults remain intentional.
- Global table defaults remain overrideable OOP defaults, not schema assumptions that need removal.
- Upload maximum is 100 MB; synchronize Filament/Livewire, PHP, Octane, and Nginx envelopes.
- Leaflet/Iconify may remain lazy CDN assets, but pin versions and avoid raw unescaped HTML helpers.

## Migrations And Seeding

- V2 retains canonical package baseline migrations because it is an application distribution, not a generic domain library.
- Fresh projects delete only known pristine Laravel migrations by exact hash, then use Accelerator's baseline.
- Existing applications use explicit transition migrations. Never delete or rewrite applied migrations blindly.
- `Model::unguard()` remains intentional for the Filament stack.
- Local demo seeding must be opt-in. Non-local seeding requires explicit bootstrap credentials and must fail rather than create predictable users.

## Telemetry v2

- V1 telemetry classes, schema, dashboard fragments, request static state, query timeline, log reader, and source-file capture are removed.
- Stop Octane and archive the old telemetry SQLite file plus WAL/SHM companions before enabling v2.
- V2 refuses unknown/v1 schemas and never auto-drops them.
- Schema `200` uses idempotent occurrence persistence and a durable notification outbox.
- Capture is authenticated Octane Swoole only. Headers/query/payload are off unless explicitly enabled.
- The truthful performance contract is no request-path disk I/O, not zero CPU or zero latency.
- Telemetry flushing uses a Swoole worker timer and does not require an Octane task worker.

## Deployment v2

The entire v1 deployment contract is replaced.

Removed:

- stage names `test` and `prod`;
- `OPS_DEPLOY_TEST_*` and `OPS_DEPLOY_PROD_*`;
- package-manager auto-detection and npm/pnpm branches;
- `larahelp` and its VPS installation;
- `deploy-slim`;
- `bootstrap-ssl`;
- shared mutable `.env` as every release's direct env target;
- global FPM reload/OPcache-reset behavior.

Required replacements:

- use `staging` and `production` with `OPS_DEPLOY_STAGING_*` / `OPS_DEPLOY_PRODUCTION_*`;
- regenerate `.env.envoy` from the v2 template; unknown legacy keys fail parsing;
- use committed `composer.lock`, `bun.lock`, and `packageManager=bun@...`;
- use `envoy init` once per stage and `envoy deploy` afterward;
- use `envoy ssl` only to resume/repair SSL and `envoy bootstrap` only to re-render scoped infrastructure;
- give each release an immutable `{root}/shared/env/{release}.env` and switch the shared env alias during deploy/rollback;
- point Nginx to `{root}/current/public` and let it enforce the shared maintenance marker;
- let the renderer own the scoped Nginx vhost and Supervisor group file;
- keep at least one Octane request worker and one Swoole task worker; increase either count only for measured capacity or concurrency needs;
- enable either Horizon or the plain queue worker, never both.

Normal deploy now rejects infrastructure drift, unsafe roots/config, dirty/unpushed Git, mixed lockfiles, runtime/deploy env leakage, destructive migrations without an explicit flag, and failed health checks.

`deploy-fresh-seed` requires the exact Indonesian confirmation phrase and remains inappropriate for production data. Rollback restores code and its matching env but never rolls back database migrations automatically.

## WSS Migration Order

Recommended bounded sequence:

1. archive v1 and freeze a baseline;
2. adopt minimal provider/config/feature boot;
3. replace fake helpers and model APIs;
4. consolidate Filament resource/theme ownership;
5. migrate authentication/OAuth and seed safety;
6. archive/reset telemetry and adopt schema `200`;
7. regenerate v2 deploy env/config without running the fresh installer;
8. validate rendering/preflight locally;
9. inspect only the authorized WSS remote root/domain/group;
10. initialize or surgically adopt the release layout, then validate HTTPS and rollback.

Do not claim migration complete merely because the package compiles. WSS must pass boot, config/route/view caches, resource/model diagnostics, frontend build, doctor, and scoped deployment acceptance.

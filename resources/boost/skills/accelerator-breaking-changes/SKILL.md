---
name: accelerator-breaking-changes
description: Migrate an existing Accelerator v1 or Laravel application to Accelerator v2 through reviewable surgical changes. Use for WSS upgrades, removed v1 contracts, schema transitions, config path migration, or compatibility cleanup; never run the fresh installer.
---

# Accelerator v2 Existing-App Migration

V2 is a clean break. There is no adopt/merge command or package compatibility facade.

## Before changes

- Preserve the last v1 tag/bundle and ignored `.accelerator_v1/` snapshot.
- Back up application and telemetry databases.
- Record routes, panels/resources, schedules, env keys, deploy root/domain/group, and services.
- Keep each migration slice bootable and reviewable.

## Required target changes

- Use Bun only and committed Composer/Bun locks.
- Keep application `User`; implement the Accelerator user contract explicitly.
- Keep Filament auth/MFA; Fortify and OAuth remain gated.
- Provision/resync a verified Super Admin with real Gate/Shield access.
- Move deployment state to `.accelerator/deploy.env` and stage runtime files to `.accelerator/environments/`.
- Keep root `Envoy.blade.php` as a thin package bridge.
- Use `staging`/`production`, immutable release envs, and `init`/`deploy`/`rollback`.
- Archive v1 telemetry SQLite/WAL/SHM before enabling strict schema `200`.
- Replace broad model/resource scanners with compact context only after consumers migrate.
- Keep sidebar/login/wizard unchanged until the separate taste-gated UI phase.

## Do not recreate removed patterns

- typed-column runtime magic/generated model PHPDoc;
- Lookup/query forwarding wrappers;
- fake provider/plugin/registry classes;
- duplicated theme inputs;
- npm/pnpm/yarn branches;
- `deploy-slim`, `larahelp`, `test`/`prod` stage aliases;
- package-level compatibility facades.

Temporary adapters may live only in the migrating application and require an explicit deletion task.

## Migration order

1. minimal provider/config boot;
2. auth/Super Admin/panel access;
3. env/config paths and configure workflow;
4. resource metadata/context/verifier consumers;
5. telemetry archive/adoption;
6. deployment renderer/permissions/release flow;
7. non-UI quality gates and staging rollback drill;
8. UI taste phase only after explicit approval;
9. remove adapters and stale skills/docs.

Never delete/rewrite applied migrations blindly. Use explicit transition migrations and stop on ambiguous data collapse.

Migration is complete only after boot/caches, static analysis, frontend build, auth/resource checks, telemetry status, and authorized scoped deployment/rollback evidence pass.

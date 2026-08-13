---
name: accelerator-project-lifecycle
description: Guide a WireNinja Accelerator application from pristine Laravel installation through local business development and Easyploy single-stage or dual-stage deployment.
---

# Accelerator project lifecycle

Accelerator owns the Laravel/Filament application foundation. Easyploy is the sole deployment control plane.

## Route by phase

- Fresh pristine install: `accelerator-installation`.
- Existing application migration: `accelerator-breaking-changes`; never run the installer.
- Local application config: `accelerator-env-config`.
- Authorized server mutation: `easyploy-deployment`.
- Read-only diagnosis: `accelerator-ops-observability`.

## Invariants

- Root `/` belongs to userland.
- pnpm is default; npm is fallback; one lockfile only.
- Never run Composer update on the VPS.
- Stable root is `/var/www/{domain}`; stable identity is `deployment_key`.
- Queues use the database driver and package-owned sub-minute scheduler drain.
- Client apps own no Supervisor, Octane, Horizon, Reverb, Nightwatch, or NightOwl daemon.
- Dual stages run identical code and isolate all mutable runtime data and credentials.
- Single-stage hides the badge; dual-stage shows local/test/live labels.
- Rollback never reverses migrations.
- Restore requires an exact stage-owned backup and matching revision.
- Production receives the exact successful staging revision through `easyploy promote`.

Read [references/workflow.md](references/workflow.md) for the end-to-end sequence.

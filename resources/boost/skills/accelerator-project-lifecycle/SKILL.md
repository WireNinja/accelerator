---
name: accelerator-project-lifecycle
description: Guide a WireNinja Accelerator application from a pristine Laravel project through installation, local development, single-stage or dual-stage configuration, first deployment, promotion, rollback, and ongoing operations. Use when planning or explaining the complete project workflow, choosing single versus dual environments, or onboarding an AI agent into a fresh Accelerator context.
---

# Accelerator project lifecycle

Treat public Artisan commands as the only user/AI interface. Deployer is the internal atomic-release engine; Supervisor owns long-running processes. Envoy is absent.

## Route by phase

- Fresh pristine install: activate `accelerator-installation`.
- Existing application migration: activate `accelerator-breaking-changes`; never run the installer.
- Local/stage configuration: activate `accelerator-env-config`.
- Authorized server mutation: activate `accelerator-deployment`.
- Read-only server diagnosis: activate `accelerator-ops-observability`.

## Non-negotiable invariants

- Root `/` belongs to userland.
- pnpm is default; npm is fallback; one lockfile only.
- Never run Composer update on the VPS.
- Stable root is `/var/www/{domain}` with `releases`, `shared`, and `current`.
- Stable deployment identity is `deployment_key`; derive Supervisor groups and service names from it.
- Reserve a scanned, explicit 20-port block; never auto-assign during deploy.
- Dual stages run identical code/features but isolate every mutable datum and process.
- Single-stage projects hide the environment badge.
- Dual-stage projects show `LOCAL DATA`, `TEST DATA`, and `LIVE DATA` badges.
- A code rollback never reverses database migrations.
- Data restore requires an exact stage-owned backup and matching active Git revision.
- Dual-stage production receives the exact successful staging revision through `accelerator:deploy:promote`.

Read [references/workflow.md](references/workflow.md) before producing a full install-to-domain plan or executing a multi-phase lifecycle. Read `../accelerator-deployment/references/backup-restore.md` before any data restore.

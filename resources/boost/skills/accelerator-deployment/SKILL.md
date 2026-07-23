---
name: accelerator-deployment
description: Operate Accelerator v2 Envoy preflight, init, deploy, rollback, recovery, Nginx, Supervisor, release envs, backups, and stage services. Use for any authorized server mutation; use ops-observability for read-only diagnosis.
---

# Accelerator Deployment

## Public flow

Verify actual tasks with `vendor/bin/envoy tasks` before execution.

```bash
vendor/bin/envoy run preflight --stage=production
vendor/bin/envoy run init --stage=production
vendor/bin/envoy run deploy --stage=production
vendor/bin/envoy run rollback --stage=production
vendor/bin/envoy run status --stage=production
```

Use `staging` or `production`; single-stage enables production only. `init` is first setup, `deploy` is continuity, and rollback changes code plus its matching env. Database rollback is manual.

## Before mutation

- Confirm SSH host, stage, domain, root, runtime user/group, and Supervisor group.
- Require clean/pushed Git and committed Composer/Bun locks.
- Validate local config, selected runtime env, remote tools/disk/runtime/ports/DNS, and existing global Nginx.
- Never inspect or mutate unrelated projects.
- Never print/commit secrets or run `composer update` on the VPS.

## Local ownership

- Root `Envoy.blade.php` is a thin committed import bridge.
- `.accelerator/deploy.env` contains only `OPS_DEPLOY_*`.
- `.accelerator/environments/{stage}.env` contains Laravel runtime keys only.
- Deferred config fails with an actionable `accelerator:configure deployment` message.

## Trusted single-VPS permission profile

Use normal Unix owner/group permissions, not mandatory ACL helpers:

- release directories `2750`, release files `0640`;
- shared writable directories `2770`;
- immutable env `0640`;
- detect the runtime group with `id -gn`;
- unrelated users receive no access.

Passwordless sudo is an explicit trusted-host prerequisite, not a hardened-security guarantee.

## Invariants

- Build and backup before maintenance.
- Nginx and Laravel both understand the shared maintenance marker.
- Each release points to immutable `{root}/shared/env/{release}.env`.
- Switch code and env atomically.
- Restart only the configured Supervisor group.
- Health must pass through generated Nginx before maintenance clears.
- A failed mutating story keeps maintenance/lock state for deliberate recovery.
- Never prune current/referenced envs or `{root}/archive`.
- Enable Horizon or the plain queue worker, never both.
- Swoole uses at least one request worker and one task worker.

Nginx/Supervisor candidates are archived and validated before replacement. Normal deploy rejects renderer drift.

## Destructive/reset operations

Fresh-seed deployment is backup-first, disposable-stage only, and requires its exact confirmation phrase. Never use it as a production migration shortcut.

## Failure handling

- Preflight/build/backup failure: active traffic remains unchanged.
- Post-maintenance migration/runtime/health failure: leave maintenance active.
- SSL failure after healthy HTTP init: report partial readiness and use the explicit SSL repair task.
- Unlock only after confirming no deployment is running; unlocking never clears maintenance.

Use `accelerator-ops-observability` for read-only status/log diagnosis.

---
name: easyploy-deployment
description: Operate a Laravel/Accelerator single-VPS project through the local Easyploy CLI. Use for stage configuration, PHP-FPM/Nginx/native-cron reconciliation, deploys, exact-revision promotion, rollback, domain-root relocation, environment changes, remote Artisan, logs, ports, stale locks, and application-aware backups. Use read-only commands for diagnosis and mutating commands only with explicit server authority.
---

# Easyploy deployment

Easyploy is the only routine deployment interface. Run it on the developer Mac from the Laravel project; manual SSH is break-glass only.

## Establish context

1. Locate `.easyploy/manifest.json`. If absent, stop unless `easyploy init` is explicitly requested.
2. Read the manifest without displaying `.easyploy/environments/*.env`.
3. Resolve the exact deployment key, stage, SSH host, domain, root, revision, and operation.
4. Run `easyploy config validate --stage=<stage> --json`.
5. Run `easyploy doctor --stage=<stage> --json` for infrastructure, or `easyploy status --stage=<stage> --json` for release/health diagnosis.

Never infer production in a dual-stage project.

## Command routing

- Code, PHP dependency, or frontend change: `easyploy deploy --stage=<stage>`.
- Verified staging to production: `easyploy promote --from=staging --to=production`; this deploys the exact staging revision.
- Domain, root, Nginx, PHP/FPM, certificate, or cron change: `doctor`, then `reconcile --dry-run`, then `reconcile`.
- Runtime env: `easyploy env edit`, `env diff`, then `env push`.
- Diagnosis: `status`, `doctor`, `logs`, or `ports`.
- Remote Artisan: `easyploy artisan --stage=<stage> -- <arguments>`.
- Code recovery: `easyploy rollback`; migrations are never reversed.
- Domain-root move: `easyploy relocate --from=/var/www/exact-old-root`; source is preserved.
- Stale lock: inspect with `status`; `unlock` only after proving no deployment is active.
- Backup: `backup create|status|local|download|restore`. `backup local` inventories downloads on the Mac without SSH. Restore requires exact stage, backup ID, disk/mode, and destructive authority.

Before mutation, report the exact stage, host, domain, root, and command. Pass `--yes` only after authority exists. After mutation, verify with `status --json`.

Do not run Certbot, Composer update, Nginx/FPM/cron edits, or symlink commands manually when Easyploy exposes the workflow.

## Boundaries

- `.easyploy/manifest.json`: committed non-secret topology.
- `.easyploy/environments`, `state.json`, and `backups`: ignored local state.
- Accelerator owns Laravel-aware backup semantics, queue scheduling, Reverb client configuration, and OpenTelemetry instrumentation.
- Easyploy owns SSH, Nginx, dedicated FPM pools, native cron, releases, env transfer, and local backup downloads.
- VPS bootstrap remains operator-owned.

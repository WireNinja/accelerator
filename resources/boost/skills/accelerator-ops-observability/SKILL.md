---
name: accelerator-ops-observability
description: Diagnose Accelerator application health, database queue drains, backups, centralized Reverb connectivity, and OpenTelemetry export without changing server state. Use Easyploy read-only commands for releases and infrastructure.
---

# Accelerator operations observability

Confirm deployment key, stage, domain, root, and SSH host from `.easyploy/manifest.json`.

```bash
php artisan accelerator:doctor --json
easyploy config validate --stage=production --json
easyploy doctor --stage=production --json
easyploy status --stage=production --json
easyploy backup status --stage=production --json
```

Evidence order: committed Easyploy topology; active release; Nginx/FPM/cron health; Laravel logs; queue backlog; backup status; centralized service connectivity. Inspect only key presence for secrets.

Centralized Reverb and OpenObserve are separate services. Diagnose client configuration first; touching either central service needs separate authority. Use `accelerator-observability` for OTLP-specific checks.

This skill is read-only. Do not deploy, restart, reconcile, roll back, unlock, restore, or rewrite config. Switch to `easyploy-deployment` only after explicit mutation authority.

---
name: accelerator-installation
description: Install or resume Accelerator v2 in a pristine Laravel 13 project. Never use for an existing application.
---

# Accelerator installation

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0@dev -W --no-interaction
php artisan accelerator:install
```

Inspect `php artisan accelerator:install --help` for non-interactive options. Non-interactive runs require `--admin-password` or `ACCELERATOR_ADMIN_PASSWORD`; never print it. Use `--json` for one stable result. Default to pnpm 11+; use npm 12+ only when requested. The installer rejects older package-manager versions because they cannot enforce the committed policy. Never install a Laravel starter kit first.

When deployment is configured during install, require a stable `--deployment-key` and explicit `--port-base`. Use `accelerator:ports` against the target SSH host before choosing a new 20-port block. Domains may change; the deployment key remains stable.

The installer owns only a verified pristine skeleton. It preserves root `/`, installs Filament/System/RBAC/auth as core, configures selected OAuth/PWA/Telegram/Horizon/Reverb/Scout/NightOwl integrations, publishes native vendor migrations by exact command/tag, copies only Accelerator-owned user/settings migrations, runs `migrate:fresh --seed`, provisions Super Admin, generates Shield permissions, builds frontend assets, installs Boost resources, and runs non-test quality checks. Vendor config is not copied wholesale.

Supply-chain policy is mandatory: one lockfile, seven-day minimum release age, no exotic transitive sources, no trust downgrade except reviewed exact-version exceptions, and explicit build-script allowlisting. Security-only exceptions currently force patched `concurrently`, `sharp`, and `filelist`; do not broaden them or disable the age window globally. An emergency age bypass must name one reviewed package/version and be removed after the window.

Files: `.env` local runtime, committed `.accelerator/deploy.json` non-secret topology, ignored stage envs, and ignored install receipt. Never treat the receipt as config.

A fresh deployment-enabled install writes a working per-stage daily backup schedule, local destination, explicit retention/health limits, and stable backup identity. Operator Telegram and offsite storage remain optional. Verify their presence through doctor/feature output; use `accelerator:notify:test` only after credentials are configured. Detailed operations live in `../accelerator-deployment/references/backup-restore.md`.

Verify command discovery, doctor JSON, Composer validation/audit, PHPStan level 5, pnpm/npm frozen install, frontend build, caches, Super Admin access, RBAC, and root-route ownership. Do not run this installer on WSS or any existing app.

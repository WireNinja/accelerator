---
name: accelerator-installation
description: Destructively install, resume, or explicitly reinstall Accelerator v2 in a Laravel 13 project.
---

# Accelerator installation

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0@dev -W --no-interaction
php artisan accelerator:install
```

Inspect `php artisan accelerator:install --help` for non-interactive options. Non-interactive runs require `--admin-password` or `ACCELERATOR_ADMIN_PASSWORD`; never print it. Use `--json` for one stable result. Reinstallation after a finished receipt additionally requires `--force`. Default to pnpm 11+; use npm 12+ only when requested. The installer rejects older package-manager versions because they cannot enforce the committed policy. Never install a Laravel starter kit first.

When realtime is selected, Accelerator generates local centralized-Reverb credentials. Easyploy stage environments own distinct staging/production credentials. Never print or commit the ignored registry or stage envs.

Deployment is deliberately absent from the installer. After application installation and commit, run `easyploy init`; its stable deployment key is independent from mutable domains. Client projects reserve no ports.

The installer owns and overwrites its recipe targets. It preserves root `/`, installs Filament/System/RBAC/auth as core, configures selected OAuth/PWA/Telegram/realtime/Scout/observability integrations, publishes native vendor migrations by exact command/tag, copies only Accelerator-owned user/settings migrations, runs `migrate:fresh --seed`, provisions Super Admin, generates Shield permissions, builds frontend assets, installs Boost resources, and runs non-test quality checks. Vendor config is not copied wholesale. A finished install receipt requires interactive confirmation or `--force` before destructive reinstallation; an unfinished receipt resumes completed steps.

Supply-chain policy is mandatory: one lockfile, seven-day minimum release age, no exotic transitive sources, no trust downgrade except reviewed exact-version exceptions, and explicit build-script allowlisting. Security-only exceptions currently force patched `concurrently`, `sharp`, and `filelist`; do not broaden them or disable the age window globally. An emergency age bypass must name one reviewed package/version and be removed after the window.

Files: `.env` local runtime, ignored `.accelerator/install-state.json` receipt, committed `.easyploy/manifest.json` topology, and ignored Easyploy stage envs. Never treat the receipt as config.

Before first deployment, fill each Easyploy stage env with independent application, database, backup/R2, Telegram, Reverb, and OTLP values. Use `easyploy backup status` after deployment; explicitly disable S3 only when same-VPS-only durability is acceptable.

Verify command discovery, doctor JSON, Composer validation/audit, PHPStan level 5, pnpm/npm frozen install, frontend build, caches, Super Admin access, RBAC, and root-route ownership. On an existing app, use this installer only when overwriting its recipe files and rebuilding its database is explicitly intended; otherwise route to `accelerator-breaking-changes`.

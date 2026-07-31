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

Inspect `php artisan accelerator:install --help` for non-interactive options. Default to pnpm 11+; use npm 12+ only when requested. The installer rejects older package-manager versions because they cannot enforce the committed policy. Never install a Laravel starter kit first.

The installer owns only a verified pristine skeleton. It preserves root `/`, installs Filament/System/RBAC/auth as core, configures selected OAuth/PWA/Telegram/Horizon/Reverb/Scout/Nightwatch integrations, runs `migrate:fresh --seed`, provisions Super Admin, generates Shield permissions, builds frontend assets, installs Boost resources, and runs non-test quality checks.

Supply-chain policy is mandatory: one lockfile, seven-day minimum release age, no exotic transitive sources, no trust downgrade except reviewed exact-version exceptions, and explicit build-script allowlisting. Security-only exceptions currently force patched `concurrently`, `sharp`, and `filelist`; do not broaden them or disable the age window globally.

Files: `.env` local runtime, committed `.accelerator/deploy.json` non-secret topology, ignored stage envs, and ignored install receipt. Never treat the receipt as config.

Verify command discovery, doctor JSON, Composer validation/audit, PHPStan level 5, pnpm/npm frozen install, frontend build, caches, Super Admin access, RBAC, and root-route ownership. Do not run this installer on WSS or any existing app.

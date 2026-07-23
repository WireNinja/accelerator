---
name: accelerator-installation
description: Install or resume WireNinja Accelerator v2 in a pristine Laravel 13 project, including Prompts onboarding, scaffold, env, frontend, Super Admin, Shield, Boost skills, and optional deployment configuration. Use for fresh installs and installer failures; never for existing applications.
---

# Accelerator Installation

## Fresh-only flow

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0@dev -W --no-interaction
bash vendor/wireninja/accelerator/bin/install
```

Use Bun. Do not install a Laravel starter kit first. Refuse non-pristine recipe targets instead of merging them.

## Onboarding contract

Collect only values that cannot be inferred safely:

- app identity; local URL defaults to `http://localhost:8000`;
- initial Super Admin identity/password;
- Livewire or Inertia Vue landing stack;
- database, Redis, and runtime features;
- optional deployment topology and real SSH/repository/domain/root values.

Never invent `server`, `.test`, a repository, deploy root, or credentials. Deployment may be deferred.

## Journaled recipe

1. `scaffold`: replace verified pristine targets; create `resources/svg/.gitkeep` first.
2. `environment`: write local env, public example, gitignore, and thin Envoy bridge.
3. `composer`: configure/install locked PHP tooling.
4. `frontend`: install with Bun only.
5. `application`: migrate fresh/seed, provision Super Admin, Shield, PWA assets, and production build.
6. `quality`: install Boost skills and run configured non-test quality gates.

Required subprocess failures are fatal. `.accelerator/install-state.json` resumes an unfinished identical plan. A completed identical rerun is a no-op. After completion, the file is a non-secret receipt, not mutable configuration.

## Generated configuration

- Root `.env`: local Laravel runtime.
- `.env.example`: public key contract without secrets.
- Root `Envoy.blade.php`: committed package bridge, even when deployment is deferred.
- `.accelerator/deploy.env`: created only from complete deployment answers.
- `.accelerator/environments/{stage}.env`: created only for enabled stages.
- No default `.env.testing`.

Use `php artisan accelerator:configure` for later changes. It must validate a draft, show a redacted diff, write atomically, and never SSH/deploy/migrate/restart.

## Acceptance

Verify the applicable scenario with `php artisan list`, doctor, config/route/view caches, static analysis, and Bun build. Prove interrupted resume and completed no-op with file/database hashes. Confirm Super Admin can open Shield roles and System settings.

Never run this installer on WSS or another existing application; use `accelerator-breaking-changes`.

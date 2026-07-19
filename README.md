# WireNinja Accelerator

An intentionally batteries-included Laravel 13 foundation for authenticated internal applications. It provides a complete Filament baseline, Inertia Vue and Livewire support, optional runtime integrations, deployment automation, development helpers, and Boost skills.

## Fresh Installation

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0 -W --no-interaction
bash vendor/wireninja/accelerator/bin/install
```

The Laravel Prompts onboarding collects application identity, initial administrator, frontend, database, optional feature activation, and optional deployment topology. The installer then owns the scaffold, env files, migrations, frontend, Filament, Shield, Boost, Pint, production build, and verification.

Do not delete Laravel files manually and do not install a starter kit first. The installer accepts only a pristine Laravel 13 skeleton, removes known default files safely, and resumes interrupted work from its ignored journal.

The installer is intentionally destructive to a fresh local database because it finishes with `migrate:fresh --seed`. Never run it against an existing application. Existing v1 applications must migrate surgically.

## Non-Interactive Installation

```bash
export ACCELERATOR_ADMIN_PASSWORD='use-a-real-secret'

bash vendor/wireninja/accelerator/bin/install \
  --no-interaction \
  --app-name='My Project' \
  --app-url=http://my-project.test \
  --frontend=inertia \
  --database=sqlite \
  --features=filament,fortify,panels,settings,ticketing,pwa,insider,scout,wayfinder
```

If `ACCELERATOR_ADMIN_PASSWORD` is absent, a secure generated password is printed once. Use Bun; npm, pnpm, Yarn, and legacy Bun lockfiles are not supported by v2.

## Optional Features

Dependencies remain installed by design. Feature selection controls runtime activation and integration, not Composer package presence.

Available features include Fortify, panels, settings, ticketing, OAuth, PWA, Telegram, telemetry, Insider, Horizon, Reverb, Scout, Nightwatch, and Wayfinder. Filament remains the internal-app core.

## Deployment

Onboarding can generate a production-only or staging-plus-production setup:

```bash
bash vendor/wireninja/accelerator/bin/install \
  --no-interaction \
  --deploy \
  --deployment-mode=single \
  --project=my-project \
  --ssh-host=server \
  --repo=git@github.com:example/my-project.git \
  --branch=main \
  --domain=app.example.com \
  --deploy-root=/var/www/app.example.com \
  --http-runtime=octane
```

Complete the generated local-only runtime seed, commit and push the application, then deploy:

```bash
vendor/bin/envoy run preflight --stage=production
vendor/bin/envoy run init --stage=production
vendor/bin/envoy run deploy --stage=production
```

Preflight is read-only. It rejects an unformatted, dirty, or unpushed local tree and an already-invalid global Nginx configuration before creating the remote stage layout.

Valid stages are `staging` and `production`. Envoy renders scoped Nginx and Supervisor configuration, initializes tracked Composer path-repository submodules, builds exact locked releases with Bun and Composer, keeps immutable per-release env files, backs up before continuous migrations, enforces maintenance in Nginx, checks `/up`, supports SSL repair and rollback, and never prunes the deployment archive.

## Local Verification

```bash
php artisan accelerator:doctor
php artisan agent:model-context User --compact
composer analyse
bun run build
```

See the installed Accelerator Boost skills for installation, configuration, Filament, telemetry, and deployment workflows.

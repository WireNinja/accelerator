---
name: accelerator-installation
description: Install WireNinja Accelerator v2 into a pristine Laravel 13 application with its Bash onboarding recipe, optional feature gates, automatic environment/frontend/Boost setup, resumable journal, and deployment seeds.
---

# Accelerator Installation

## Scope

Use this skill for a new Accelerator v2 application, installer failure/resume, rerun verification, or install-time deployment configuration.

The Bash installer is deliberately **fresh-only**. It replaces the Laravel skeleton, runs `migrate:fresh --seed`, and refuses modified recipe targets. Do not run it against an existing application such as WSS; migrate existing projects surgically.

## Canonical Flow

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0 --no-interaction
bash vendor/wireninja/accelerator/bin/install
```

Interactive onboarding asks only for application identity, frontend, database, Redis, runtime features, and optional deployment details. It then owns the complete setup: `.env`, configs, migrations, Inertia Vue or Livewire entrypoint, Filament panel, frontend dependencies/build, Shield, Pint, Boost, and doctor.

Use Bun for JavaScript dependencies and builds. Do not run a starter-kit installer before Accelerator.

## Non-Interactive Install

```bash
bash vendor/wireninja/accelerator/bin/install \
  --no-interaction \
  --app-name='My Project' \
  --app-url=http://my-project.test \
  --frontend=inertia \
  --database=sqlite \
  --features=filament,fortify,panels,settings,ticketing,pwa,insider,scout,wayfinder
```

Supported values:

- `--frontend=inertia|livewire`
- `--database=sqlite|mysql|pgsql`
- `--redis` uses Redis for cache, sessions, and queues; without it, database drivers work immediately.
- `--features=` accepts `filament`, `fortify`, `panels`, `settings`, `ticketing`, `oauth`, `pwa`, `telegram`, `telemetry`, `insider`, `horizon`, `reverb`, `scout`, `nightwatch`, and `wayfinder`.

Dependencies remain installed when optional runtime features are inactive. Feature gates control boot and integration, not Composer package presence.

## Installer Contract

The recipe executes these journaled steps:

1. `scaffold` — replace only pristine Laravel recipe files and migrations.
2. `environment` — render `.env` / `.env.example`, optional deploy seeds, and gitignore entries.
3. `composer` — configure autoload/scripts and install Boost, Envoy, Larastan, Rector, and PHPStan rules.
4. `frontend` — install the generated frontend with Bun.
5. `application` — autoload, translations, `migrate:fresh --seed`, storage link, Shield, optional PWA icons, and production build.
6. `quality` — configure Boost for `wireninja/accelerator`, install all package skills, run Pint, and run doctor.

Any failed required process returns non-zero. State lives in ignored `.accelerator/install-state.json`; rerunning the same command resumes after the last completed step. Once finished, rerun exits successfully without touching files or the database.

The saved plan is authoritative during resume. Do not expect different CLI flags to replace a partially completed plan.

## Generated Defaults

- Authentication is internal-facing; public registration is absent.
- OAuth is disabled unless selected; selected OAuth starts in `existing_only` mode.
- File upload policy is 100 MB. The recipe sets FPM `upload_max_filesize=100M`, PHP/Nginx request envelopes to 110 MB, and the Octane Supervisor command to the same PHP limits.
- Themes are discovered from `resources/css/filament/**/theme.css`.
- `resources/svg` and the public favicon are created before icon-dependent commands run.
- Accelerator is added to `boost.json`; missing Accelerator skills fail installation instead of reporting false success.

## Deployment Setup

Add deployment during onboarding or non-interactively:

```bash
bash vendor/wireninja/accelerator/bin/install \
  --no-interaction \
  --app-name='My Project' \
  --app-url=http://my-project.test \
  --frontend=inertia \
  --database=pgsql \
  --features=filament,fortify,panels,settings,horizon,reverb,nightwatch \
  --redis \
  --deploy \
  --project=my-project \
  --ssh-host=onidel \
  --repo=git@github.com:example/my-project.git \
  --domain=app.example.com \
  --deploy-root=/var/www/app.example.com \
  --http-runtime=octane
```

This creates `Envoy.blade.php`, `.env.envoy`, `.env.staging`, and `.env.production`. The three env files are local-only and gitignored.

- Only `OPS_DEPLOY_*` keys belong in `.env.envoy`.
- Runtime application keys belong in `.env.staging` / `.env.production`.
- Fill real credentials locally; never invent or commit production secrets.
- Use an absolute shared database path for deployed SQLite.
- Never enable Horizon and the plain queue worker together.

Use the deployment skill before running Envoy against a server.

## Verification

```bash
php artisan accelerator:doctor
php artisan agent:model-context User --compact
php artisan config:cache
php artisan route:list
composer analyse
bun run build
```

Expected model commands are `agent:model-context` and Laravel's native `model:show`; v1 `agent:model-outline`, `agent:model-audit`, and `agent:model-doc` must not exist.

For a completed-install no-op check, record the database and project file hashes, rerun the identical Bash command, and confirm both hashes are unchanged.

## Existing Applications

Do not force the fresh installer over application code. For WSS or another v1 application:

1. keep a local ignored `.accelerator_v1` snapshot;
2. install the v2 package dependency;
3. apply schema, config, provider, auth, Filament, telemetry, and deployment changes in explicit reviewable slices;
4. use `accelerator:doctor`, model context, static analysis, build, and migration checks after every slice;
5. remove all compatibility adapters before declaring the migration complete.

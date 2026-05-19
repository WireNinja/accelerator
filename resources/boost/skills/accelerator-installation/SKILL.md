---
name: accelerator-installation
description: Install WireNinja Accelerator into fresh or migrated Laravel projects with non-interactive flags, deployment env files, Boost resources, optional PWA, per-component config publishing, and post-install Shield/Pint hooks.
---

# Accelerator Installation

## When To Use

Installing or reinstalling `wireninja/accelerator`, migrating a project onto Accelerator conventions, preparing a project for Envoy deployment, refreshing generated Boost resources, or adding the Accelerator Laravel PWA Vite package.

## Core Rules

- Keep userland thin. Prefer Accelerator defaults and package resources over copied project-local scripts.
- `OPS_DEPLOY_*` keys belong only in `.env.envoy`.
- `.env.staging` and `.env.production` are local-only runtime seed files. Sensitive keys are blanked when the seed files are generated — operator fills them manually before scp.
- Do not overwrite an existing Inertia frontend unless the user explicitly asks.
- Use Bun for JavaScript package installation in WireNinja projects.
- Never invent production secrets.

## Composer Install

```bash
composer require wireninja/accelerator:^1.1 --no-interaction
```

Pin a known patch:

```bash
composer require wireninja/accelerator:1.1.x --no-interaction
```

After package changes:

```bash
php artisan package:discover --ansi
```

## Discover Components

```bash
php artisan accelerator:install --list-components
```

Prints a table of available wizard components (`reverb`, `filament-core`, `octane`, `localization`, `app-config`, `frontend-core`) with stub/config/command counts. Use this before crafting `--component=` flags.

## Verify-only Check (no modification)

```bash
php artisan accelerator:install --check
```

Delegates to `agent:audit`, runs the integration check (PHP version, extensions, OPcache, Accelerator integration, env keys) without changing anything.

## Fresh Interactive Install

```bash
php artisan accelerator:install
```

Opens the component wizard.

## Fresh Non-Interactive Install

Full install (all components):

```bash
php artisan accelerator:install --no-interaction --force --preset=full --with-boost
```

Selected components:

```bash
php artisan accelerator:install --no-interaction --component=reverb --component=octane --component=app-config --with-boost
```

Skip components from a preset:

```bash
php artisan accelerator:install --no-interaction --preset=full --without=frontend-core --with-boost
```

Use `--without=frontend-core` when the target project already has a real Inertia React/Vue/Svelte frontend that must be preserved.

## Skip Migrate Finalisation

For installs that should not touch the database (e.g. running outside of a normal `migrate` window):

```bash
php artisan accelerator:install --no-interaction --preset=app --no-migrate
```

`--no-migrate` skips `php artisan migrate`, `storage:unlink/link`, and `webpush:vapid` finalisation steps.

## Per-Component Config Publishing

Configs (`config/*.php`) are scoped to selected components — installing only `reverb` will publish only `broadcasting.php` + `reverb.php`, not the entire stub config tree.

| Component | Configs published |
|---|---|
| reverb | broadcasting.php, reverb.php |
| filament-core | filament.php, filament-shield.php, fortify.php, permission.php, livewire.php, media-library.php, query-builder.php |
| octane | octane.php |
| app-config | app.php, auth.php, cache.php, database.php, filesystems.php, logging.php, mail.php, queue.php, services.php, session.php, settings.php, activitylog.php, backup.php, **backup_predeploy.php**, blade-icons.php, horizon.php, pennant.php, scout.php, webpush.php, laravel-pdf.php |
| frontend-core | inertia.php |
| localization | (none) |

`backup_predeploy.php` is the Spatie profile used by Envoy `db-backup`. Do not delete it from `app-config`.

## Post-Install Hooks

After component install / config publish:

- **Shield** — runs `shield:safe-regenerate` automatically when `filament-core` or `app-config` was selected. Idempotent. Disable with `--without-shield`.
- **Pint** — runs `vendor/bin/pint --format=agent` automatically when the binary exists. Disable with `--without-pint`.
- **Env summary** — `EnvReader::redacted()` summary printed at the end with empty/missing keys list, to help operators see what still needs filling.

Force-enable when the auto-detection misses:

```bash
php artisan accelerator:install --no-interaction --preset=app --with-shield --with-pint
```

## Deployment Files

```bash
php artisan accelerator:install --no-interaction --preset=none --with-deploy --with-boost \
  --stage-mode=single \
  --default-stage=prod \
  --project=ssm \
  --ssh-host=onidel \
  --repo=git@github.com:WireNinja/smart-school-management.git \
  --domain=ssm.pgsduksw.web.id \
  --root=/var/www/ssm.pgsduksw.web.id \
  --group=ssm_prod \
  --octane-port=9020 \
  --reverb-port=9021 \
  --nightwatch-port=2420 \
  --php-bin=/usr/bin/php8.5 \
  --bun-bin=/home/adhi/.bun/bin/bun
```

Generated files:

```text
Envoy.blade.php
.env.envoy
.env.staging
.env.production
```

The installer also adds the three env seed files to `.gitignore`.

### Sensitive credential handling in seed files

When `.env.staging` / `.env.production` are generated from local `.env`, the following keys have their **values blanked** (keys retained for shape compatibility):

`APP_KEY`, `DB_PASSWORD`, `REDIS_PASSWORD`, `MAIL_PASSWORD`, `PUSHER_APP_SECRET`, `REVERB_APP_SECRET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `GOOGLE_CLIENT_SECRET`, `STRIPE_SECRET`, `MEILISEARCH_KEY`, `NIGHTWATCH_TOKEN`, `TELEGRAM_BOT_TOKEN`, `VAPID_PRIVATE_KEY`, `SENTRY_DSN`, `SENTRY_LARAVEL_DSN`.

If any were populated in local `.env`, the installer warns the operator at the end of `--with-deploy`. Fill the seed files manually before `vendor/bin/envoy run init/deploy`.

## Single-Stage Projects

Production-only:

```text
OPS_DEPLOY_DEFAULT_STAGE=prod
OPS_DEPLOY_TEST_ENABLED=false
OPS_DEPLOY_PROD_ENABLED=true
```

```bash
vendor/bin/envoy run deploy --stage=prod
```

Do not force a fake `test` stage.

## Two-Stage Projects

```text
OPS_DEPLOY_DEFAULT_STAGE=test
OPS_DEPLOY_TEST_ENABLED=true
OPS_DEPLOY_PROD_ENABLED=true
```

```bash
vendor/bin/envoy run deploy --stage=test
vendor/bin/envoy run deploy --stage=prod
```

## Runtime Env Seeds

`.env.staging` / `.env.production` MUST be key-compatible with `.env`.

For SQLite:

```dotenv
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=/var/www/example.com/shared/database/database.sqlite
# DB_USERNAME=root
# DB_PASSWORD=
# DB_SOCKET=
```

On a release-based VPS, SQLite must use a shared absolute path. Do not keep the live database inside a release directory.

For Reverb:

```dotenv
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
# REVERB_SERVER_HOST=0.0.0.0
# REVERB_SERVER_PORT=8080
```

## PWA Setup

```bash
php artisan accelerator:install --no-interaction --preset=none --with-pwa --with-boost
```

Then add the Vite plugin without disturbing existing Inertia plugins:

```ts
import { laravelPwa } from '@wireninja/vite-plugin-laravel-pwa';

laravelPwa({
    name: 'Application Name',
    shortName: 'App',
    description: 'Application description',
    themeColor: '#111827',
});
```

Generate icons from `public/favicon.svg`:

```bash
bunx laravel-pwa icons
```

Verify:

```bash
bun run build
```

## Boost Resources

```bash
php artisan boost:update --ansi
```

If `laravel/boost` isn't installed, the installer warns and continues. To install:

```bash
composer require laravel/boost
```

Expected Accelerator skills:

```text
accelerator-installation
accelerator-deployment
accelerator-env-config
accelerator-filament
accelerator-model-outline
accelerator-ops-observability
accelerator-pwa-development
```

## CI / Git Hooks (optional)

```bash
php artisan accelerator:install --no-interaction --preset=none --with-ci --with-hooks --force
```

`--with-ci` writes `.github/workflows/deploy.yml` — tag-triggered (or `workflow_dispatch`) Envoy deploy job. Required GitHub secrets:

| Secret | Purpose |
|---|---|
| `DEPLOY_SSH_PRIVATE_KEY` | SSH key with VPS access |
| `DEPLOY_SSH_HOST` | VPS hostname / IP |
| `DEPLOY_SSH_USER` | VPS deploy user |
| `ENV_ENVOY` | full content of `.env.envoy` |
| `ENV_PRODUCTION` | full content of `.env.production` seed |
| `ENV_STAGING` | full content of `.env.staging` seed |

`--with-hooks` writes `.git/hooks/pre-commit`. Runs Pint on staged PHP (auto re-stage) + PHPStan on the project. Bypass per-commit: `SKIP_HOOK=1 git commit ...`. Skipped when `.git` is missing or hook already exists (use `--force` to overwrite).

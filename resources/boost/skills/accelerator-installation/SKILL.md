---
name: accelerator-installation
description: Install WireNinja Accelerator into fresh or migrated Laravel projects with non-interactive flags, deployment env files, Boost resources, and optional PWA setup.
---

# Accelerator Installation

## When To Use

Use this skill when installing or reinstalling `wireninja/accelerator`, migrating a project onto Accelerator conventions, preparing a project for Envoy deployment, refreshing generated Boost resources, or adding the Accelerator Laravel PWA Vite package.

## Core Rules

- Keep userland thin. Prefer Accelerator defaults and package resources over copied project-local scripts.
- Do not place `OPS_DEPLOY_*` keys in runtime env files.
- `.env.envoy` is local-only deploy wiring.
- `.env.staging` and `.env.production` are local-only runtime seed files.
- Do not overwrite an existing Inertia frontend unless the user explicitly asks for it.
- Use Bun for JavaScript package installation in WireNinja projects unless the project explicitly uses another package manager.
- Never invent production secrets. Prepare keys and placeholders, then let the human fill secrets.

## Composer Install

Use the latest tagged Accelerator release:

```bash
composer require wireninja/accelerator:^1.1 --no-interaction
```

If pinning a known patch:

```bash
composer require wireninja/accelerator:1.1.x --no-interaction
```

After package changes:

```bash
php artisan package:discover --ansi
```

## Fresh Interactive Install

```bash
php artisan accelerator:install
```

This opens the component wizard.

## Fresh Non-Interactive Install

Full install:

```bash
php artisan accelerator:install --no-interaction --force --preset=full --with-boost
```

Install only selected components:

```bash
php artisan accelerator:install --no-interaction --component=reverb --component=octane --component=app-config --with-boost
```

Skip components from a preset:

```bash
php artisan accelerator:install --no-interaction --preset=full --without=frontend-core --with-boost
```

Use `--without=frontend-core` when the target project already has a real Inertia React/Vue/Svelte frontend that must be preserved.

## Deployment Files

Generate local deployment files:

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

## Single-Stage Projects

For a simple production-only project:

```text
OPS_DEPLOY_DEFAULT_STAGE=prod
OPS_DEPLOY_TEST_ENABLED=false
OPS_DEPLOY_PROD_ENABLED=true
```

The command is then:

```bash
vendor/bin/envoy run deploy --stage=prod
```

Do not force a fake `test` stage for a project that only has production.

## Two-Stage Projects

For internal test plus production:

```text
OPS_DEPLOY_DEFAULT_STAGE=test
OPS_DEPLOY_TEST_ENABLED=true
OPS_DEPLOY_PROD_ENABLED=true
```

Deploy with:

```bash
vendor/bin/envoy run deploy --stage=test
vendor/bin/envoy run deploy --stage=prod
```

## Runtime Env Seeds

`.env.staging` and `.env.production` must be key-compatible with `.env`.

For SQLite, do not activate empty MySQL-only keys:

```dotenv
DB_CONNECTION=sqlite
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=/var/www/example.com/shared/database/database.sqlite
# DB_USERNAME=root
# DB_PASSWORD=
# DB_SOCKET=
```

On a release-based VPS deployment, SQLite must use a shared absolute path. Do not keep the live database inside a release directory.

For Reverb, keep client-facing values active and only activate server bind values when needed:

```dotenv
REVERB_HOST="localhost"
REVERB_PORT=8080
REVERB_SCHEME=http
# REVERB_SERVER_HOST=0.0.0.0
# REVERB_SERVER_PORT=8080
```

## PWA Setup

Install Accelerator PWA support:

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

Generate icons from:

```text
public/favicon.svg
```

Using Bun:

```bash
bunx laravel-pwa icons
```

Verify:

```bash
bun run build
```

## Boost Resources

Refresh generated AI guidance:

```bash
php artisan boost:update --ansi
```

Expected Accelerator skills include:

```text
accelerator-installation
accelerator-deployment
accelerator-env-config
accelerator-filament
accelerator-model-outline
accelerator-ops-observability
accelerator-pwa-development
```

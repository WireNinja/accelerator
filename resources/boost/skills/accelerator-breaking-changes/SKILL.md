---
name: accelerator-breaking-changes
description: Breaking changes and required userland actions when upgrading wireninja/accelerator. Also lists non-breaking improvements per version for AI agent awareness.
---

# Accelerator Breaking Changes

Format per entry:
- **🔴 BREAKING** = Userland must take action or deploy will fail.
- **🟡 AWARENESS** = No action required, but behavior changed. AI agents should know.

---

## v1.1.68

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Backup listing handles application names with spaces

The Envoy `backups` story now parses backup paths without splitting an application name such as `Supervisi Akademik` into invalid size fields. This fixes `unbound variable` failures while listing predeploy or scheduled backup files.

**Action required**: None. Upgrade before using `vendor/bin/envoy run backups` for applications whose `APP_NAME` contains spaces.

---

## v1.1.67

No deployment contract changes beyond v1.1.66.

### 🟡 AWARENESS — Nginx bootstrap preserves existing SSL vhosts

`bootstrap-nginx` and `bootstrap-ssl` now inspect Let's Encrypt certificates through `sudo` and use a boolean SSL-config guard. This prevents a configured HTTPS vhost from being replaced with the HTTP-only template merely because the deploy user cannot stat root-owned certificate links.

**Action required**: Upgrade to this patch before re-running `bootstrap` on an HTTPS stage.

---

## v1.1.66

### 🔴 BREAKING — Deployment runtime and managed services are now explicit

New generated deploy env files default to PHP-FPM and no Supervisor-managed application services. Existing Octane deployments must declare their runtime and enabled programs before the next `bootstrap`:

```dotenv
OPS_DEPLOY_{STAGE}_HTTP_RUNTIME=octane
OPS_DEPLOY_{STAGE}_OCTANE_WORKERS=1
OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS=0
OPS_DEPLOY_{STAGE}_HORIZON_ENABLED=true
OPS_DEPLOY_{STAGE}_REVERB_ENABLED=true
OPS_DEPLOY_{STAGE}_SCHEDULER_ENABLED=true
OPS_DEPLOY_{STAGE}_NIGHTWATCH_ENABLED=true
```

`OPS_DEPLOY_{STAGE}_HTTP_RUNTIME=fpm` instead renders PHP-FPM Nginx locations using `OPS_DEPLOY_{STAGE}_FPM_SOCKET` and does not start Octane. Reverb websocket Nginx configuration is emitted only when Reverb is enabled. Nightwatch now actually renders `nightwatch:agent` when enabled.

For Swoole, Envoy renders `octane:swoole` so `0` genuinely disables task workers; Laravel Octane's public dispatcher otherwise converts `--task-workers=0` back to its `auto` fallback.

Apps using Redis queue without Horizon may set:

```dotenv
OPS_DEPLOY_{STAGE}_HORIZON_ENABLED=false
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_ENABLED=true
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_CONNECTION=redis
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_QUEUE=default
OPS_DEPLOY_{STAGE}_QUEUE_WORKER_PROCESSES=1
```

**Action required**: Before re-running `vendor/bin/envoy run bootstrap --stage={stage}`, set `_HTTP_RUNTIME` and explicit service flags in `.env.envoy`. Do not enable Horizon and `QUEUE_WORKER_ENABLED` together. When Nightwatch is enabled, set runtime `NIGHTWATCH_ENABLED=true` and keep `NIGHTWATCH_INGEST_URI` aligned with `_NIGHTWATCH_PORT`.

### 🟡 AWARENESS — Deploy env has a package-owned base template

The package now ships `.base-env.envoy.example`, and `accelerator:install --with-deploy` renders project `.env.envoy` from it. Existing projects can retain their manually configured deploy env; no overwrite occurs without `--force`.

The base runtime example now defaults to `NIGHTWATCH_ENABLED=false` with `LOG_STACK=daily`. Projects enabling Nightwatch must opt into both the collector and `daily,nightwatch` logging stack.

---

## v1.1.65

No breaking changes.

### 🟡 AWARENESS — Fresh-seed bootstrap loads Composer autoload

The confirmed `deploy-fresh-seed` production bypass now loads `vendor/autoload.php` before bootstrapping Laravel in the one-off PHP process. This fixes the v1.1.64 failure where `bootstrap/app.php` could not resolve `Illuminate\Foundation\Application`.

**Action required**: None. Use the same explicit confirmation flag.

---

## v1.1.64

No breaking changes.

### 🟡 AWARENESS — Fresh-seed deploy works in production

`deploy-fresh-seed` now explicitly unprohibits Laravel's `migrate:fresh` command inside the already-confirmed Envoy flow before running:

```bash
php artisan migrate:fresh --seed --force
```

This is required because Accelerator globally calls `DB::prohibitDestructiveCommands(app()->isProduction())`. The bypass is scoped to the one-off console kernel call inside `prepare-laravel-fresh-seed`; normal production commands remain protected.

**Action required**: None. Use the same explicit confirmation flag as v1.1.63.

---

## v1.1.63

No breaking changes.

### 🟡 AWARENESS — Destructive fresh-seed deploy story

New Envoy story:
```bash
vendor/bin/envoy run deploy-fresh-seed --stage=test --i-understand-this-will-drop-and-reseed-database="aku mengkonfirmasi remigrate fresh seed"
```

This story is intentionally destructive. It runs the normal release build path with Composer dev dependencies available, enters maintenance mode, runs `php artisan migrate:fresh --seed --force`, then prunes dev dependencies with the production Composer install before switching `current`.

Use it only when the operator explicitly wants a fresh database and fresh seeded data. Existing database data is destroyed after the pre-deploy backup.

**Action required**: None. Additive feature.

---

## v1.1.60

### 🔴 BREAKING — Nginx stub renamed and rewritten

Old stub `nginx-vhost.conf.stub` no longer exists. Replaced by two stubs:
- `nginx-vhost-http.conf.stub` — HTTP-only, used when no SSL cert exists
- `nginx-vhost-ssl.conf.stub` — Full SSL + HTTP/2 + HTTP/3 (QUIC)

Both stubs now use the `@octane` named location pattern (`try_files $uri @octane`) instead of the old `upstream` block + direct `proxy_pass`.

**Action required**: Re-run `vendor/bin/envoy run bootstrap --stage={stage}` to regenerate Nginx config. Bootstrap now auto-detects SSL cert and uses the correct stub.

### 🔴 BREAKING — `bootstrap-nginx` now guards against SSL downgrade

If existing Nginx config has `ssl_certificate` but no cert file is found at `/etc/letsencrypt/live/{domain}/`, bootstrap will **skip** instead of overwriting. This prevents accidental SSL → HTTP downgrade.

**Action required**: None if certs are in place. If bootstrap skips unexpectedly, verify the certificate path and restore the archived vhost before retrying.

### 🟡 AWARENESS — New `bootstrap-ssl` story

New story to obtain SSL cert and upgrade Nginx config in one command:
```bash
vendor/bin/envoy run bootstrap-ssl --stage=test
```

Uses `certbot certonly --webroot` (does NOT use `certbot --nginx` plugin which conflicts with QUIC). Then renders the SSL stub and reloads nginx.

**Action required**: None. Additive feature. Replaces manual certbot + manual nginx edit.

---

## v1.1.59

No breaking changes. Added this skill file.

---

## v1.1.58

### 🔴 BREAKING — `larahelp` v2.0

Old `larahelp` v1 on VPS is incompatible with the new release layout expectations. The new version follows symlinks, auto-detects SQLite, and respects env vars.

**Action**: Update the binary on every VPS:
```bash
scp vendor/wireninja/accelerator/stubs/vps/larahelp onidel:/tmp/larahelp
ssh onidel 'sudo mv /tmp/larahelp /usr/local/bin/larahelp && sudo chmod 755 /usr/local/bin/larahelp'
```

### 🟡 AWARENESS — `prepare-layout` enhanced

Now auto-creates `storage/framework/{views,cache,sessions}`, sets ownership, applies ACL, creates SQLite file. No action needed — backward-compatible.

---

## v1.1.57

### 🟡 AWARENESS — `clear-cache` task added to deploy flow

`optimize:clear` now runs on `current` before `db-backup`. Prevents stale config cache from breaking backup commands. No action needed.

### 🟡 AWARENESS — `backups` story added

New story `vendor/bin/envoy run backups --stage=test` lists predeploy and scheduled backup files. No action needed.

---

## v1.1.56

### 🔴 BREAKING — `OPS_DEPLOY_BUN_BIN` renamed to `OPS_DEPLOY_NPM_BIN`

Deploy will fail if the old key is still used.

**Action**: Rename in `.env.envoy`:
```diff
- OPS_DEPLOY_BUN_BIN=/path/to/bun
+ OPS_DEPLOY_NPM_BIN=pnpm
```

Per-stage override also renamed: `OPS_DEPLOY_{STAGE}_BUN_BIN` → `OPS_DEPLOY_{STAGE}_NPM_BIN`.

If the key is empty, auto-detects: pnpm → bun → npm.

### 🔴 BREAKING — `accelerator:install --bun-bin` renamed to `--npm-bin`

**Action**: Update any CI scripts or automation using `--bun-bin` to `--npm-bin`.

### 🟡 AWARENESS — Blade compiler collision fixed in `renderStub`

`vendor/bin/envoy run bootstrap` now correctly renders stub placeholders. Previously stubs were uploaded raw. No action needed unless you have manual workarounds to remove.

### 🟡 AWARENESS — Conditional `build-release` per package manager

`build-release` now branches based on configured NPM_BIN value:
- `pnpm` → `pnpm install --frozen-lockfile`
- `bun` → `bun install --frozen-lockfile`
- `npm` → `npm ci --no-audit --no-fund`

No action needed — handled automatically by the renamed key.

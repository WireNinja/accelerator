---
name: accelerator-breaking-changes
description: Breaking changes and required userland actions when upgrading wireninja/accelerator. Also lists non-breaking improvements per version for AI agent awareness.
---

# Accelerator Breaking Changes

Format per entry:
- **🔴 BREAKING** = Userland must take action or deploy will fail.
- **🟡 AWARENESS** = No action required, but behavior changed. AI agents should know.

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

**Action required**: None if certs are in place. If bootstrap skips unexpectedly, verify cert path or use `--force`.

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

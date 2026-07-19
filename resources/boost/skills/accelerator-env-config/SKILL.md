---
name: accelerator-env-config
description: Work with Accelerator v2 runtime env files, strict deployment config, Laravel config defaults, EnvReader redaction, and the explicit link-preload middleware without mixing configuration boundaries.
---

# Accelerator v2 Env And Config

## Ownership

| File | Purpose | Committed |
|---|---|---|
| package `.base-env.example` | canonical runtime-key template | yes |
| application `.env.example` | public runtime-key contract, no secrets | yes |
| application `.env` | local runtime values | no, mode `0600` |
| application `.env.staging` | staging runtime seed | no, mode `0600` |
| application `.env.production` | production runtime seed | no, mode `0600` |
| package `.base-env.envoy.example` | canonical deploy-key template | yes |
| application `.env.envoy` | local deployment orchestration | no, mode `0600` |

Runtime and deployment configuration are separate namespaces:

- Laravel runtime files must never contain `OPS_DEPLOY_*`.
- `.env.envoy` must contain only `OPS_DEPLOY_*` plus comments/blank lines.
- Deploy stage selection does not belong in Laravel config.
- Never call `env()` outside config files. Read behavior through `config()` after Laravel boots.
- Do not publish package config merely to copy defaults; package config remains env-driven.
- Never print or commit secret values.

## Runtime Contract

Fresh onboarding writes `.env` and `.env.example` from one package template. It generates the local `APP_KEY` and selected Reverb credentials; the public example keeps secrets blank.

Generated deployment runtime seeds:

- set `APP_ENV=production`, `APP_DEBUG=false`, `LOG_LEVEL=error`;
- generate independent `APP_KEY` values per stage;
- set stage-specific `https://` app/Reverb endpoints;
- generate independent Reverb credentials when selected;
- use `{root}/shared/database/database.sqlite` for SQLite;
- use `{project}_{stage}` as the default external database name;
- leave operator-owned database passwords and third-party secrets blank.

Before deploy, the selected runtime seed must have a valid app key, exact stage URL, database connection, database name, and database username for non-SQLite drivers. External services still require real operator credentials; onboarding cannot infer them.

Keep explicit frontend-safe Vite values. Do not use nested values such as `VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"`, because build-time env parsing may expose the literal reference.

## Deployment Config Contract

`DeploymentConfig` is the only parser and validator for `.env.envoy`. It rejects:

- malformed or duplicate lines;
- keys outside the v2 allowlist;
- legacy `TEST` / `PROD` keys;
- a disabled or unknown stage;
- unsafe roots, commands, identifiers, domains, branches, or repository values;
- invalid email, bcrypt hash, boolean, integer, socket, or systemd values;
- Horizon and plain queue worker enabled together;
- reused ports among enabled local services;
- fewer than one Octane request worker, or a negative Swoole task-worker count.

Valid stages are `staging` and `production`.

Shared keys define:

- default stage, project, SSH alias, repository, branch, and retained releases;
- PHP version/binary, Bun binary, runtime user, and SSL email;
- initial administrator identity and bcrypt password hash.

Per-stage keys define:

- enabled flag, domain, root, Supervisor group, and direct-DNS expectation;
- FPM or Octane HTTP runtime;
- optional FPM pool/socket/service overrides;
- Octane server, port, workers, and task workers;
- Horizon or plain queue worker settings;
- Reverb, Scheduler, and Nightwatch settings.

Prefer the PHP-version-derived FPM socket/service. Fill explicit overrides only for a nonstandard VPS. `DNS_DIRECT=false` is valid only when a deliberate proxy/CDN fronts the origin.

`OCTANE_TASK_WORKERS=0` is a valid explicit default. Increase it only when application code actually uses Swoole task dispatch; Accelerator telemetry v2 does not require a task worker.

## Laravel Configuration

- Read Accelerator behavior from `config('accelerator.*')`.
- Runtime detection is process-based: `is_octane_runtime()` and `is_swoole_runtime()` inspect the actual process; do not recreate `SERVER_RUNTIME` flags.
- Feature flags control provider/route/runtime activation, not dependency installation.
- Rebuild configuration and route caches after changing a feature flag.
- `LauncherEnum` is an optional application-owned extension. Accelerator must not create fake launcher entries.

## Link Preload

`AddLinkHeadersForPreloadedAssets` is available through the `link_preload` alias. Apply it explicitly to Inertia/Vite route groups. It is not global and is not needed by Filament's Livewire navigation.

```php
Route::middleware(['inertia', 'link_preload'])->group(function (): void {
    // Inertia routes.
});
```

## Redacted Inspection

Use the existing reader instead of ad-hoc env output:

```bash
php artisan accelerator:env
php artisan accelerator:env --json --compact
php artisan config:show accelerator
```

`EnvReader::redacted()` masks secret-like tokens including password, secret, token, auth, private, VAPID, webhook, signature, bearer, credential, and DSN. Public/ID keys remain visible unless a stronger private/secret token is also present. Empty and missing values are reported explicitly.

When comparing env files, compare their key contract first. Values are intentionally different across local, staging, and production.

Use `accelerator-deployment` for Envoy sequencing and remote mutation. Use `accelerator-ops-observability` for read-only runtime checks.

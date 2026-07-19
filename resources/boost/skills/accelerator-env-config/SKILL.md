---
name: accelerator-env-config
description: Work with WireNinja Accelerator env files, config defaults, EnvReader, link-preload middleware alias, and Envoy deploy env without bypassing Laravel config.
---

# Accelerator Env And Config

## When To Use

Adding or reviewing Accelerator config keys, `.env.example`, `.base-env.example`, `.env.envoy` key shape, `accelerator:env`, link-preload middleware alias, or any code that reads runtime/deploy configuration.

Use `accelerator-deployment` for actual Envoy deploy flow, Nginx/Supervisor generation, release layout, maintenance, rollback, or server-side service behavior.
Use `accelerator-ops-observability` for read-only runtime diagnosis after deployment.

## Rules

- Read runtime behavior from `config('accelerator.*')`.
- Do NOT call `env()` directly outside config files — `env()` returns null after `config:cache`.
- Keep package config env-driven so applications can update the package without republishing config.
- Add new env keys to `vendor/wireninja/accelerator/.base-env.example` (library source).
- Add project-specific env keys to the application `.env.example` only when the project needs concrete values.
- Treat `.env` as local/server runtime state. Do not print secrets in responses.
- Use `WireNinja\Accelerator\Support\EnvReader` or existing Artisan commands for env inspection.
- Keep `.env`, `.env.staging`, `.env.production`, `.env.example`, and `.base-env.example` key-compatible for runtime application keys.
- Do not put `OPS_DEPLOY_*` keys in runtime env files or examples. Those keys belong only in `.env.envoy`.
- Keep `.env.envoy` limited to `OPS_DEPLOY_*` keys and formatted into readable sections.
- Package deploy defaults live in `.base-env.envoy.example`; `accelerator:install --with-deploy` renders it into the ignored project-root `.env.envoy`.
- Do not use nested references for `VITE_*` keys (e.g. `VITE_REVERB_APP_KEY="${REVERB_APP_KEY}"`). Vite may expose the literal string. Use explicit frontend-safe values.

## Accelerator Config Keys

`config/accelerator.php` (env-driven, not published unless customised):

```php
return [
    'infra' => [
        'hosting' => env('INFRA_HOSTING', 'dedicated'),
    ],

    'proxy' => [
        'trust_local' => env('ACCELERATOR_TRUST_LOCAL_PROXY', true),
    ],

    'enums' => [
        'role' => RoleEnum::class,
        'resource' => ResourceEnum::class,
        'panel' => PanelEnum::class,
        'launcher' => enum_exists(LauncherEnum::class) ? LauncherEnum::class : null,
    ],

    'horizon' => [
        'auto_register' => true,
        'email_to' => env('HORIZON_EMAIL_TO'),
    ],

    'dev' => [
        'login_default' => env('DEV_LOGIN', null),
        'password_default' => env('DEV_PASSWORD', null),
    ],
];
```

`LauncherEnum` is an application-owned optional extension point. Fresh installs do not create a fake launcher or a project-specific external URL. When an application adds `App\Enums\System\LauncherEnum`, Accelerator discovers it through the conditional config default and renders its cases in the shared sidebar. Panel links are also filtered against Filament's registered panel IDs, so an enum case never creates a navigation link to an inactive panel.

Runtime detection is intentionally not env-driven. Accelerator is opinionated for Octane Swoole when running under Octane, and uses the current PHP process marker instead:

- `is_octane_runtime()` checks `$_SERVER['LARAVEL_OCTANE'] === '1'`.
- `is_swoole_runtime()` checks Octane plus the loaded Swoole extension.
- Do not reintroduce `SERVER_RUNTIME` or `ACCELERATOR_CACHE_ALLOW_*`; cache/session choices are resolved by their own Laravel config and runtime-specific code paths.

### Link Preload

`AddLinkHeadersForPreloadedAssets` is available via the `link_preload` middleware alias. Apply it explicitly on route groups that serve Inertia/Vite assets. It is NOT applied globally — Filament admin uses Livewire wire-navigate and does not benefit from preload headers.

Usage in `routes/web.php`:

```php
Route::middleware(['inertia', 'link_preload'])->group(function () {
    // Inertia frontend routes
});
```

## EnvReader

`WireNinja\Accelerator\Support\EnvReader::redacted()` returns an associative array with sensitive values masked. Token-based matching (split on `_`) covers:

- `key`, `secret`, `password`, `token`, `auth`, `pass`, `crypt`, `salt`, `vapid`, `private`, `access`
- `webhook`, `signature`, `cipher`, `bearer`, `cred`, `credential`, `dsn`

Whitelist tokens: `id`, `public` un-mark sensitive **unless** `secret` or `private` is also present.

Example outputs:

| Key | Output |
|---|---|
| `DB_PASSWORD` | `[REDACTED]` |
| `GOOGLE_CLIENT_ID` | actual value (id token wins) |
| `OPENID_TOKEN` | `[REDACTED]` (token-based, no false negative) |
| `VAPID_PUBLIC_KEY` | actual value (public + key, public wins) |
| `PRIVATE_KEY_ID` | `[REDACTED]` (private overrides id) |
| `STRIPE_WEBHOOK_SECRET` | `[REDACTED]` |
| `SENTRY_DSN` | `[REDACTED]` |
| empty value | `[EMPTY]` (literal `0` is preserved as `0`, not `[EMPTY]`) |
| missing key | `[MISSING]` |

JSON output for AI agents:

```bash
php artisan accelerator:env --json --compact
```

Returns `{status, summary{total,redacted,empty,missing,set}, values}`.

## Checks

```bash
php artisan config:show accelerator
php artisan accelerator:env
php artisan accelerator:env --json --compact
```

When comparing env files, compare keys first. Values may intentionally differ between the package base example, project example, local `.env`, and server `shared/.env`.

## `.env.envoy` Shape

`.base-env.envoy.example` is the package template; installed projects receive its rendered output as `.env.envoy`, which carries deploy wiring. Required:

- `OPS_DEPLOY_DEFAULT_STAGE`
- shared deploy defaults (repo, branch, PHP bin, JavaScript package manager bin, run user, SSL email, KEEP_RELEASES)
- per-stage `TEST` / `PROD` domain, root, group, and `HTTP_RUNTIME` (`fpm` or `octane`)
- `OPS_DEPLOY_PHP_VERSION` as the preferred PHP intent and `_FPM_POOL` for optional dedicated FPM pools
- `_FPM_SOCKET` / `_FPM_SERVICE` only as advanced FPM overrides; Octane server, port, and explicit worker counts for Octane
- service flags for Horizon or plain queue worker, Reverb, Scheduler, and Nightwatch
- Reverb and Nightwatch ports when those services are enabled

`OPS_DEPLOY_{STAGE}_HTTP_RUNTIME=fpm` routes Nginx through the configured FPM socket and health-checks through Nginx. `octane` requires `OPS_DEPLOY_{STAGE}_OCTANE_PORT` and health-checks the Octane process directly.
For FPM, prefer `OPS_DEPLOY_PHP_VERSION=8.5` (or `8.4`) plus optional `OPS_DEPLOY_{STAGE}_FPM_POOL=pool-name`. Envoy derives `phpX.Y`, `/run/php/phpX.Y-fpm[-pool].sock`, and the matching systemd service. Keep `_FPM_SOCKET` and `_FPM_SERVICE` blank unless the VPS uses non-standard names.
`OPS_DEPLOY_{STAGE}_OCTANE_WORKERS` defaults to `1`. For Swoole, `OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS` also defaults to `1` and cannot be zero because Laravel Octane's default server tick uses the task queue. Increase the count only when application concurrency needs it.
Use either `OPS_DEPLOY_{STAGE}_HORIZON_ENABLED=true` or `OPS_DEPLOY_{STAGE}_QUEUE_WORKER_ENABLED=true`, never both. The latter renders a bounded `queue:work` Supervisor program suitable for Redis queue apps without Horizon.

Runtime deploy intent belongs only in `.env.envoy` via `OPS_DEPLOY_{STAGE}_HTTP_RUNTIME` and, for Octane, `OPS_DEPLOY_{STAGE}_OCTANE_SERVER`. Do not add deploy runtime selector keys back to Laravel runtime `.env` files.

For task sequencing, this skill owns the deploy env contract and redaction rules only. Do not use it as the runbook for `init`, `deploy`, `rollback`, Nginx bootstrap, Supervisor, or live server triage; use `accelerator-deployment` or `accelerator-ops-observability` for those.

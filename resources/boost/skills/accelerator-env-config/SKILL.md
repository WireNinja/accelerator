---
name: accelerator-env-config
description: Work with WireNinja Accelerator env files, config defaults, EnvReader, link-preload middleware alias, and Envoy deploy env without bypassing Laravel config.
---

# Accelerator Env And Config

## When To Use

Adding or reviewing Accelerator config keys, `.env.example`, `.base-env.example`, `.env.envoy`, `accelerator:env`, link-preload middleware alias, or any code that reads deployment/runtime settings.

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
        'launcher' => LauncherEnum::class,
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

`.env.envoy` carries deploy wiring. Required:

- `OPS_DEPLOY_DEFAULT_STAGE`
- shared deploy defaults (repo, branch, PHP/Bun bin, run user, SSL email, KEEP_RELEASES)
- per-stage `TEST` / `PROD` domain, root, group, runtime
- per-stage Octane port, explicit Octane worker counts, and Reverb / Nightwatch ports
- per-stage enable flags

Per-stage `OPS_DEPLOY_{STAGE}_OCTANE_PORT` is REQUIRED — Envoy `health-check` curls Octane directly using that port.
`OPS_DEPLOY_{STAGE}_OCTANE_WORKERS` defaults to `1`. For Swoole, `OPS_DEPLOY_{STAGE}_OCTANE_TASK_WORKERS` defaults to `0`; set a positive task-worker count only when the application dispatches Octane tasks.

Runtime deploy intent belongs only in `.env.envoy` via explicit `OPS_DEPLOY_{STAGE}_RUNTIME`. Do not add runtime selector keys back to Laravel runtime `.env` files.

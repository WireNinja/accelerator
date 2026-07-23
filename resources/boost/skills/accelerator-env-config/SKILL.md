---
name: accelerator-env-config
description: Inspect or change Accelerator v2 application env, feature activation, deployment configuration, stage runtime files, redacted env output, and link-preload behavior. Use for `.env`, `.accelerator` config, feature toggles, or deployment readiness without mutating a server.
---

# Accelerator Env and Configuration

## One source per concern

| File | Purpose |
|---|---|
| `.env` | Local Laravel runtime. |
| `.env.example` | Committed public key contract. |
| `.accelerator/deploy.env` | Ignored `OPS_DEPLOY_*` orchestration only. |
| `.accelerator/environments/{stage}.env` | Ignored Laravel runtime for enabled stage. |
| `.accelerator/install-state.json` | Install resume/receipt only. |

All secret files are local-only and mode `0600`. Do not create `.env.testing` by default. Never duplicate deploy state in JSON or store project secrets in `vendor/`.

## Rules

- Call `env()` only in config files; application code reads `config()`.
- Never mix `OPS_DEPLOY_*` with Laravel runtime keys.
- Generate independent APP_KEY/Reverb credentials per stage.
- Leave external DB/OAuth/Nightwatch/Telegram/VAPID credentials incomplete until supplied.
- Use the shared absolute SQLite path for deployed stages.
- Keep explicit `VITE_*` values; do not rely on nested env interpolation.
- Rebuild config/route caches after feature changes.
- Feature flags control boot/routes; installed dependencies are not proof of activation.

## Mutation workflow

Use `php artisan accelerator:configure`. Before mutation:

1. read the real target files, not the install receipt;
2. validate the full in-memory draft;
3. show a redacted diff and affected files;
4. confirm topology/secret replacement;
5. write via same-directory temp and atomic rename;
6. run local validation only and print the next command.

Configuration must never SSH, deploy, migrate, or restart services.

## Inspection

Use `accelerator:env` if present and `config:show accelerator`. Report missing/empty keys without printing secrets. `link_preload` is explicit for Inertia/Vite route groups, never global and unnecessary for Filament navigation.

Use `accelerator-deployment` for server mutation.

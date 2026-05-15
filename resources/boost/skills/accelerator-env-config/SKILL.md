---
name: accelerator-env-config
description: Work with WireNinja Accelerator env files, config defaults, EnvReader, and generated environment checks without bypassing Laravel config.
---

# Accelerator Env And Config

## When To Use

Use this skill when adding or reviewing Accelerator config keys, `.env.example`, `.base-env.example`, `accelerator:env`, `ops:env-check`, or any code that reads deployment/runtime settings.

## Rules

- Read runtime behavior from `config('accelerator.*')`.
- Do not call `env()` directly outside config files.
- Keep package config env-driven so applications can update the package without republishing config.
- Add new env keys to `packages/accelerator/.base-env.example`.
- Add project-specific env keys to the application `.env.example` only when the project needs concrete values.
- Treat `.env` as local/server runtime state. Do not print secrets in responses.
- Use `WireNinja\Accelerator\Support\EnvReader` or existing Artisan commands for env inspection when available.

## Checks

Use these commands before changing deploy/runtime env behavior:

```bash
php artisan config:show accelerator
php artisan accelerator:env
php artisan ops:env-check --stage=test
```

When comparing env files, compare keys first. Values may intentionally differ between the package base example, project example, local `.env`, and server `shared/.env`.

## Expected Deploy Env Shape

Deployment config should support:

- `OPS_DEPLOY_DEFAULT_STAGE`
- shared deploy defaults such as repo, branch, PHP binary, run user, and SSL email
- stage-specific `TEST` and `PROD` domain/root/group/runtime
- stage-specific Octane, Reverb, and Nightwatch ports
- stage-specific service enable flags when needed

Do not make `SERVER_RUNTIME` override stage runtime accidentally. Prefer explicit `OPS_DEPLOY_{STAGE}_RUNTIME` for deploy stages.

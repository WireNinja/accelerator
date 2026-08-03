## WireNinja Accelerator v2

Accelerator is a proprietary, batteries-included foundation for Laravel 13 Filament monoliths. Optimize for solo-developer DX, package maintainability, then deterministic AI operation.

### Skill routing

- fresh install: `accelerator-installation`
- complete fresh-to-live workflow: `accelerator-project-lifecycle`
- existing-app migration: `accelerator-breaking-changes`
- env/features/deploy topology: `accelerator-env-config`
- Filament/Shield/resources/UI: `accelerator-filament`
- models/schema/casts/relations: `accelerator-model-context`
- audit logging: `accelerator-activity-log`
- PWA/Vite: `accelerator-pwa-development`
- remote mutation: `accelerator-deployment`
- read-only operations: `accelerator-ops-observability`
- Nightwatch MCP issue triage: `accelerator-nightwatch-mcp`

### Fixed contract

- Fresh flow: `laravel new` → Composer require → `php artisan accelerator:install`.
- Installer is fresh-only, resumable, and may run `migrate:fresh --seed`; existing apps migrate surgically.
- Filament, System settings, Shield RBAC, activity log, custom sidebar/topbar, and Filament auth/MFA are core.
- Root `/` is userland-owned.
- pnpm is default; npm is fallback. Bun/Yarn and mixed lockfiles are unsupported.
- Inertia, Vue, Wayfinder, Fortify, ticketing, custom telemetry, Insider, Envoy, and generated GitHub Actions are absent.
- `Model::unguard()`, searchable/preloaded Selects, overrideable table defaults, image editing, 100 MB uploads, VerticalWizard, and LocationPicker are intentional.
- Use native Laravel/Filament/package behavior before creating Accelerator abstractions.

### Configuration boundaries

- `.env`: local Laravel runtime; application code reads `config()`.
- `.accelerator/deploy.json`: committed non-secret topology.
- `.accelerator/environments/{stage}.env`: ignored stage secrets.
- `.accelerator/install-state.json`: ignored resume receipt only.
- Deployment root is `/var/www/{domain}` with Deployer releases and `current` symlink.
- `deployment_key` is stable; Supervisor groups are `acc-{deployment_key}-{stage}`. `port_base` reserves one 20-port block per project.
- Normal server operations originate from local Artisan commands. Deployer, SSH, Supervisor, and Linux commands are internal implementation layers; manual SSH is break-glass only.
- Dual stages are independent, identical application instances. Code, dependencies, features, UI, and behavior match; domain and mutable data/runtime state are isolated. The configurable `LOCAL DATA`, `TEST DATA`, or `LIVE DATA` topbar badge is the deliberate UI exception. Single-stage projects do not show it.

### Safety

- Discover public workflows through Artisan `--help`.
- Never print/commit secrets, run `composer update` on a VPS, mutate unrelated hosts, auto-rollback migrations, or infer release/tag/push authority.
- Confirm stage/domain/root/host before remote mutation. Read-only diagnosis does not mutate.
- PHPStan/Larastan level 5 is minimum; do not hide errors with baselines or ignores.

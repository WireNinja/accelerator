## WireNinja Accelerator v2

Accelerator is a proprietary, batteries-included foundation for Laravel 13 Filament monoliths. Optimize for solo-developer DX, package maintainability, then deterministic AI operation.

### Skill routing

- fresh install: `accelerator-installation`
- complete fresh-to-live workflow: `accelerator-project-lifecycle`
- existing-app migration: `accelerator-breaking-changes`
- env/features/deploy topology: `accelerator-env-config`
- Filament/Shield/resources/UI: `accelerator-filament`
- end-to-end business features: `accelerator-feature-development`
- models/schema/casts/relations: `accelerator-model-context`
- audit logging: `accelerator-activity-log`
- PWA/Vite: `accelerator-pwa-development`
- remote mutation: `accelerator-deployment`
- read-only operations: `accelerator-ops-observability`
- self-hosted NightOwl observability: `accelerator-nightowl`

### Fixed contract

- Fresh flow: `laravel new` → Composer require → `php artisan accelerator:install`.
- Installer is fresh-only, resumable, and may run `migrate:fresh --seed`; existing apps migrate surgically.
- Filament, System settings, Shield RBAC, activity log, custom sidebar/topbar, and Filament auth/MFA are core.
- Root `/` is userland-owned.
- pnpm is default; npm is fallback. Bun/Yarn and mixed lockfiles are unsupported.
- Inertia, Vue, Wayfinder, Fortify, ticketing, custom telemetry, Insider, Envoy, and generated GitHub Actions are absent.
- `Model::unguard()`, searchable/preloaded Selects, overrideable table defaults, image editing, 100 MB uploads, VerticalWizard, and LocationPicker are intentional.
- Use native Laravel/Filament/package behavior before creating Accelerator abstractions.

### Solo-developer execution contract

- Inspect repository state, installed versions, sibling conventions, and available commands before asking questions. Ask only when a missing decision materially changes business behavior, data design, authorization, security, destructive scope, cost, or external state.
- The owner controls product intent and final trade-offs. Verify technical claims and challenge conflicts with concrete repository or version evidence.
- Keep simple CRUD native. Extract a named Action for a real business operation and a Service for a reusable capability or integration; do not generate architecture ceremonially.
- Prefer native framework behavior, then existing dependencies, then a small direct implementation. Add a dependency only when it removes meaningful complexity and is maintained, compatible, narrowly scoped, and supply-chain acceptable.
- Use constructor injection in application classes. Add interfaces only at real external or replaceable boundaries.
- Comments explain why, invariants, non-obvious constraints, or important trade-offs. Never narrate obvious code. Use PHPDoc for contracts and static-analysis shapes, not giant model documentation.
- Do not create, modify, or delete test files unless the owner explicitly requests tests in the current prompt. Existing relevant tests may run because they are read-only. Otherwise prefer Pint, PHPStan/Larastan, Accelerator verifiers, command diagnostics, and focused authenticated UI checks. This project rule overrides generic Boost guidance that would require creating or updating tests for every change.
- Inspect Git status before editing and committing. Commit all approved task changes, but never silently include unrelated work or secrets. Use `git add -A` only after every detected change is confirmed for the checkpoint.
- If an implementation mistake occurs, state it plainly, report concrete impact, recover safely, and do not claim success without evidence.

### Application architecture invariants

- Treat all backend code as Octane-sensitive: no request-derived state in static properties or singletons; use scoped bindings and method-time request/auth resolution.
- Policies are the authorization boundary. Filament visibility is not security; Shield regeneration must use Accelerator's safe workflow.
- Multi-write business invariants belong in transactions with database constraints or locks where races are possible. Durable side effects run after commit.
- `Model::unguard()` is intentional application-wide behavior. Do not add noisy `$fillable` arrays merely to simulate protection; validate and authorize at input and action boundaries.

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

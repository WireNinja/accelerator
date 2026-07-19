## WireNinja Accelerator v2

Accelerator is an intentionally batteries-included foundation for internal Laravel applications. Dependency breadth is deliberate; runtime activation and boot cost must remain explicit.

### Installation

- The supported fresh flow is `laravel new` → `composer require wireninja/accelerator:^2.0 -W` → `bash vendor/wireninja/accelerator/bin/install`.
- The Bash installer is fresh-only, uses one Laravel Prompts onboarding plan, and may run `migrate:fresh --seed`.
- Never run the fresh installer against an existing application. Migrate existing applications surgically.
- Interactive and `--no-interaction` modes use the same planner, journal, and recipe.
- The ignored `.accelerator/install-state.json` makes an interrupted install resumable and an identical completed rerun a no-op.
- Bun is the only supported frontend package manager. Do not add npm/pnpm/yarn branches.
- Filament is the internal-app core. Other selected features control runtime activation while their dependencies remain installed.
- `resources/svg/.gitkeep`, the shared Filament theme, env files, migrations, Super Admin, Shield, Boost skills, Pint, doctor, and the production frontend build are installer-owned invariants.

### Runtime Configuration

- Read Accelerator behavior from `config('accelerator.*')`; never call `env()` outside config files.
- Runtime env files contain Laravel keys only. Deploy orchestration lives only in ignored `.env.envoy` with `OPS_DEPLOY_*` keys.
- `.env`, `.env.envoy`, `.env.staging`, and `.env.production` are local-only mode-`0600` files.
- Feature env changes require rebuilt config and route caches.
- `link_preload` is an explicit middleware alias for Inertia/frontend route groups, not global middleware.
- Upload policy is 100 MB with a 110 MB PHP/Nginx request envelope.
- Accelerator targets authenticated internal apps; guest exception telemetry and public registration are not supported defaults.

### Authentication

- Filament owns the ready-made login, reset, verification, and MFA experience.
- Fortify is an optional headless backend feature; it is not auto-discovered when inactive.
- OAuth is opt-in. Default `existing_only` authenticates pre-provisioned users; `allowed_domains` is the explicit provisioning mode.
- OAuth never stores provider access or refresh tokens.
- Suspended users must be rejected across Filament, Fortify, OAuth, normal sessions, and impersonation.
- `Model::unguard()` is an intentional Filament-stack invariant.

### Deployment

- Valid stages are `staging` and `production`; a single-stage install enables only production.
- Public flow: `vendor/bin/envoy run init --stage={stage}` once, then `vendor/bin/envoy run deploy --stage={stage}`.
- `init` owns layout, env, code build, migration, initial administrator, Nginx, Supervisor, SSL, health, and pruning. There is no bootstrap ceremony in the happy path.
- `bootstrap` and `ssl` are expert repair stories. `deploy-slim`, `bootstrap-ssl`, `test`, and `prod` are removed v1 contracts.
- Every deploy requires clean/pushed Git, `composer.lock`, `bun.lock`, Bun `packageManager`, Pint, strict env boundaries, and exact remote SHA.
- Every release has an immutable env under `{root}/shared/env`; both code and env move together on deploy/rollback.
- Nginx checks the shared Laravel maintenance marker before PHP/Octane/static/websocket handling, except `/up` and ACME.
- Nginx and Supervisor files are rendered, scoped, archived before replacement, validated, and drift-checked.
- Never enable Horizon and the plain queue worker together. Swoole always has at least one request worker; task workers are explicit and may be `0` when the application does not use task dispatch.
- FPM is not globally reloaded; immutable release realpaths avoid stale OPcache keys and cross-project restarts. Supervisor-managed stage processes are restarted by scoped group.
- Build and migration failures before maintenance do not interrupt traffic. Failures after maintenance leave it active for rollback/repair.
- `deploy-fresh-seed` is destructive, backup-first, and requires its exact confirmation phrase.
- Rollback switches code plus its matching env; database rollback remains manual.

### Server Safety

- Confirm stage/domain/root/group before mutation and touch only that scope.
- Never inspect or change unrelated server projects as part of an Accelerator operation.
- Do not commit deploy env files or print their secrets.
- Do not run `composer update` on a server.
- Do not clear maintenance after a failed health check.
- Do not delete `{root}/archive` during release pruning.

### Telemetry

- Telemetry v2 is authenticated Octane Swoole exception capture with no request-path disk I/O.
- Swoole rows are acknowledged only after durable SQLite commit; notifications use a durable leased outbox.
- Schema `200` is strict. Archive v1/unknown databases rather than dropping them automatically.
- Use `telemetry:status --json`, not direct SQLite guesses, for health inspection.

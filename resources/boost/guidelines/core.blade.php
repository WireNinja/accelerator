## WireNinja Accelerator v2

Accelerator is a batteries-included foundation for authenticated Laravel monoliths. Dependency breadth is intentional; runtime boot and service activation must remain explicit.

### Skill routing

Read the matching skill before acting:

- fresh install/resume: `accelerator-installation`
- v1/existing-app migration: `accelerator-breaking-changes`
- env/features/configuration: `accelerator-env-config`
- Filament/Shield/resources/UI: `accelerator-filament`
- models/casts/relationships/context: `accelerator-model-context`
- audit logging: `accelerator-activity-log`
- PWA/Vite assets: `accelerator-pwa-development`
- telemetry: `accelerator-telemetry`
- deploy/init/rollback/server mutation: `accelerator-deployment`
- read-only runtime diagnosis: `accelerator-ops-observability`

Use framework/package skills too when the task crosses domains. Verify available commands before running them; v2 development plans are not proof that a command is implemented.

### Core contract

- Fresh flow: `laravel new` → require Accelerator → package Bash installer.
- Fresh installer may rewrite a pristine skeleton and `migrate:fresh --seed`; never run it on an existing app.
- WSS and other existing apps migrate surgically.
- Bun is the only frontend package manager.
- Filament is core. Optional dependencies may remain installed while providers/routes/services stay gated.
- Local URL defaults to `http://localhost:8000`.
- `resources/svg/.gitkeep`, one discoverable theme, Super Admin, Shield, build, Pint, Boost skills, and doctor are installer invariants.
- `Model::unguard()`, searchable/preloaded Selects, overrideable table defaults, and 100 MB uploads are intentional.

### Configuration boundaries

- Laravel runtime: root `.env`; read through `config()`, never `env()` outside config files.
- Deployment: ignored `.accelerator/deploy.env` containing only `OPS_DEPLOY_*`.
- Stage runtime: ignored `.accelerator/environments/{stage}.env` containing Laravel keys only.
- Installation state: ignored `.accelerator/install-state.json`; resume/receipt only, never mutable config.
- Do not create `.env.testing` unless the project explicitly needs it.
- Feature changes require config/route cache rebuild.

### Authority and safety

- User chooses patch/minor/major/exact release; never tag or publish implicitly.
- Confirm stage/domain/root/group before server mutation; never touch unrelated projects.
- Never print/commit secrets, run `composer update` on the VPS, clear failed maintenance, or auto-rollback database migrations.
- Context commands are navigation. Source, framework registry, policy, database, and runtime state are truth.
- Custom sidebar/login/wizard refactors require explicit user taste approval.

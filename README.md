# WireNinja Accelerator

Proprietary, batteries-included Laravel 13 foundation for a solo developer building authenticated Filament monoliths. Public source access grants no right to use, copy, modify, or redistribute the package; see `LICENSE`.

## Fresh installation

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0@dev -W --no-interaction
php artisan accelerator:install
```

The installer is intentionally fresh-only. It may rewrite a verified pristine Laravel skeleton and run `migrate:fresh --seed`. Never run it on an existing application. Existing applications migrate surgically.

Interactive installation uses Laravel Prompts. Deterministic installation is discoverable through:

```bash
php artisan accelerator:install --help
```

Non-interactive installation requires `--admin-password` or `ACCELERATOR_ADMIN_PASSWORD`; Accelerator never invents or prints an administrator password. Add `--json` for one stable, non-ANSI result suitable for an AI agent. The resumable receipt records each exact native migration publish and the files it produced.

The default frontend package manager is pnpm 11+; npm 12+ is supported as a fallback. Bun and Yarn are unsupported. The generated project records the exact executable version, has exactly one lockfile, and enforces a seven-day minimum release age. pnpm also blocks exotic transitive sources, trust downgrades, and unapproved dependency builds. npm disables lifecycle scripts globally and explicitly rebuilds only Sharp. Narrow release-age exceptions and overrides exist only for reviewed security fixes.

Never globally disable the release-age window. For an urgent reviewed security patch, add one exact package/version exclusion, update the lock, verify audit/build, then remove the exclusion after seven days.

Accelerator never owns `/`. Laravel's welcome page, a landing page, or a redirect is entirely userland code.

## Deliberate defaults

- Filament Admin and System panels, Shield RBAC, settings, activity log, media integration, custom sidebar/topbar, and native Filament authentication/MFA are core.
- OAuth, PWA, Telegram, Horizon, Reverb, Scout, and Nightwatch are optional runtime integrations.
- Inertia, Vue, Wayfinder, Fortify, ticketing, custom telemetry, Insider, Envoy, and generated GitHub Actions are not part of Accelerator.
- `Model::unguard()` is intentional for schema-controlled Filament forms. A project may call `Model::reguard()` in its app provider.
- Tables default to `id desc`, cursor pagination, deferred loading, compact filters, and Indonesian formatting.
- Selects default to searchable/preloaded/non-native; use `->preload(false)` for high-cardinality relationships.
- File uploads retain the image editor and a configurable 100 MB application limit.
- `VerticalWizard`, Advanced Choice, and the package Location Picker remain supported primitives.

## Daily commands

```bash
php artisan accelerator:doctor --json
php artisan accelerator:doctor --section=deployment --json
php artisan accelerator:dependencies --json
php artisan accelerator:feature:list --json
php artisan accelerator:context model User
php artisan accelerator:context resource user
php artisan accelerator:make-resource Product --panel=admin --group='Master Data' --icon=lucide-package --model --migration --factory --json
php artisan accelerator:verify-resource product --compact
php artisan accelerator:configure
composer phpstan
pnpm audit
pnpm run build
```

`accelerator:make-resource` delegates generation to Filament, applies only navigation metadata requested by the caller, regenerates Shield safely, and verifies registration/model/policy invariants. Resource labels, icons, policies, and panel placement remain readable in the resource itself. Navigation group label/icon/order is app-owned in `App\Enums\System\NavigationGroup`.

## Configuration ownership

| File | Authority |
|---|---|
| `.env` | Local Laravel runtime and feature state. |
| `.env.example` | Public environment contract. |
| `.accelerator/deploy.json` | Committed, mutable, non-secret deployment topology. |
| `.accelerator/environments/{stage}.env` | Ignored Laravel runtime secrets for a stage. |
| `.accelerator/install-state.json` | Ignored installation resume receipt; never mutable configuration. |

Read runtime values through `config()`, not `env()` outside config files. Telegram credentials, OAuth secrets, database passwords, app keys, and deployment runtime secrets never belong in database settings or `deploy.json`.

Accelerator publishes only its small app-owned config contract. Vendor configuration remains vendor-owned unless the application explicitly publishes and customizes it. All table/settings migrations live in the generated application: native package migration commands are run once during fresh installation, while Accelerator's custom user/settings migrations are copied explicitly. Runtime package migrations are deliberately forbidden.

## Deployment

Deployer v8 is the atomic release engine behind public Artisan commands. The stable project root is `/var/www/{domain}` and releases live below it with a `current` symlink.

Run normal lifecycle operations from the development machine. Artisan is the public control plane, Deployer is the atomic release engine, SSH is transport, Linux commands are remote primitives, and Supervisor owns long-running services. Manual SSH is reserved for break-glass recovery.

```bash
php artisan accelerator:configure deployment
php artisan accelerator:configure deployment --deployment-key=waringin --port-base=9010
php artisan accelerator:configure deployment --migrate-legacy --force --json --no-interaction
php artisan accelerator:configure environment --stage=production
php artisan accelerator:configure environment --stage=production --rotate-app-key --rotate-reverb-credentials
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy:init --stage=production --revision=<full-commit-sha>
php artisan accelerator:deploy --stage=production --revision=<full-commit-sha>
php artisan accelerator:deploy:promote --from=staging --to=production
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:deploy:unlock --stage=production
php artisan accelerator:deploy:relocate --stage=production --old-root=/var/www/old.example.com
php artisan accelerator:env:edit --stage=production
php artisan accelerator:env:validate --stage=production --json
php artisan accelerator:env:diff --stage=production --json
php artisan accelerator:env:push --stage=production
php artisan accelerator:service:status all --stage=production --json
php artisan accelerator:service:start octane --stage=production
php artisan accelerator:service:stop octane --stage=production
php artisan accelerator:service:restart octane --stage=production
php artisan accelerator:logs laravel --stage=production --lines=200
php artisan accelerator:backup --stage=production --only=all
php artisan accelerator:ports --host=ssh-alias --range=9000-9999 --available=20
```

Configuration never SSHes. Changing a domain recalculates `/var/www/{domain}` and reports the exact relocation command; it never moves remote files implicitly. Remote mutators confirm stage, domain, root, and host; non-interactive mutation requires explicit force. Deployment uses committed Composer/pnpm/npm locks, installs rather than updates dependencies, backs up the database before migrations, switches releases atomically, restarts only configured services, and never auto-rolls back database migrations. A failed post-switch health check may restore the previous code symlink, but database review remains manual.

`deploy:init` idempotently creates the first stage database before the normal backup-and-migrate pipeline. Pass `--revision=<full-commit-sha>` when two stages must receive the same immutable release. SQLite is created in stage-owned shared storage. Local MySQL/MariaDB and PostgreSQL databases are created through passwordless sudo and granted to an already-existing application account; Accelerator never copies or prints its password. External database servers must be provisioned explicitly. Ordinary deploys never create databases.

Deployment schema 2 stores one stable `deployment_key` plus one explicit `port_base`. Domains may change without renaming the deployment. Supervisor groups are derived as `acc-{deployment_key}-{stage}`; arbitrary `service_group` values are forbidden. One project reserves 20 ports: dual-stage staging uses offsets 0-9 and production uses offsets 10-19, while single-stage production uses offsets 0-9. Octane, Reverb, and Nightwatch use offsets 0, 1, and 2. Scan the host and choose the block explicitly; deployment never auto-assigns ports.

The ignored local `.accelerator/environments/{stage}.env` file is canonical. `env:diff` compares it with the server without printing values. `env:push` uploads atomically, clears cached configuration, restarts only the derived stage group, and health-checks. Manual remote `.env` editing is an emergency operation because a later deploy will overwrite it.

Use `--ssl-email` to replace the existing ACME email explicitly. `--rotate-app-key` and `--rotate-reverb-credentials` are intentional stage-scoped credential rotations; do not use either casually on an established live stage.

Shared Laravel storage and cache paths use inherited ACLs for both the deploy user and runtime user. Releases become rollback candidates only after services pass the retried HTTPS health check; incomplete or failed releases are marked bad and never selected as rollback targets.

File backups contain mutable `storage/app` data, not the Git-managed release tree. This keeps uploads recoverable without archiving dependencies, build output, or the `.env` secret symlink. Applications may override the include list through `accelerator.backup.include` when they own additional mutable paths.

Supervisor is the sole owner of long-running service restarts. Accelerator deliberately omits Laravel's generic post-deploy `artisan reload`, which would duplicate the restart and cannot signal Supervisor processes owned by the runtime user.

Certbot obtains or reuses certificate material through the dedicated ACME webroot; it does not rewrite the Nginx virtual host. Accelerator remains the single owner and renderer of both HTTP and HTTPS configuration.

The deploy recipe clones the application repository and initializes only the tracked `packages/accelerator` submodule before Composer runs. It intentionally does not recurse through unrelated submodules, so a broken or optional gitlink elsewhere cannot widen deployment scope.

`deploy:init` and ordinary deploy run a read-only ownership/collision preflight before mutation. It rejects unmanaged roots, duplicate Nginx domains, duplicate Supervisor groups/programs, and occupied Octane/Reverb/Nightwatch ports. Accelerator-written roots and service files carry deployment/stage ownership markers, so `--force` never means “take over another project”. A recognized older Accelerator-owned group for the exact same stage/domain/root may be renamed deterministically during provisioning.

Dual-stage deployment runs two independent, identical instances of the same application. Code, dependencies, features, UI, and deployment behavior remain identical. Only the domain and each instance's mutable data/runtime state are separate: SQL data, uploads, cache, queues, sessions, credentials, keys, logs, and processes. Never use staging as a differently configured edition of the application.

Production promotion deploys the exact Git revision from the successful, health-checked staging release. It never substitutes the latest branch head.

Dual-stage projects show a persistent Filament topbar badge so local, test, and live data cannot be confused. This badge is the deliberate UI exception to the identical-instance rule. `accelerator:configure deployment` enables it automatically in local, staging, and production env files; single-production projects keep it hidden. Each env controls presentation through `ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED`, `ACCELERATOR_ENVIRONMENT_INDICATOR_LABEL`, and `ACCELERATOR_ENVIRONMENT_INDICATOR_COLOR`. Defaults are `LOCAL DATA`/`info`, `TEST DATA`/`warning`, and `LIVE DATA`/`danger`; changing the label or any valid Filament badge color does not require a package edit. Composer installation or updates deliberately never mutate these env values.

Dual stages on one VPS must also have distinct `REDIS_PREFIX`, `CACHE_PREFIX`, `HORIZON_NAME`, `HORIZON_PREFIX`, and `SESSION_COOKIE` values. `accelerator:configure environment` derives those namespaces from `{deployment_key}_{stage}` so queues, cache, sessions, and Horizon state cannot cross stage boundaries even when both stages use the same Redis server.

`accelerator:configure environment` synchronizes stage runtime feature flags, and deployment refuses to start when Horizon, Reverb, or Nightwatch flags disagree with their stage service topology. Installed packages alone are not proof that their runtime providers are enabled.

Horizon and a plain queue worker are mutually exclusive. One VPS per stage is supported; clusters, containers, microservices, and CI orchestration are deliberately out of scope.

## Package maintenance

```bash
composer validate --strict --no-check-publish
composer audit --locked
composer outdated --direct
composer phpstan
../../vendor/bin/pint --dirty --format agent
```

PHPStan/Larastan level 5 is the minimum. Do not add a baseline or suppress real errors. Do not create a release, tag, or push unless the owner explicitly chooses the version.

## AI skill routing

| Work | Skill |
|---|---|
| Fresh install/resume | `accelerator-installation` |
| Existing-app migration | `accelerator-breaking-changes` |
| Env/features/deploy topology | `accelerator-env-config` |
| Filament/Shield/resources/UI | `accelerator-filament` |
| Models/schema/casts/relations | `accelerator-model-context` |
| Activity logging | `accelerator-activity-log` |
| PWA/Vite assets | `accelerator-pwa-development` |
| Nightwatch MCP triage | `accelerator-nightwatch-mcp` |
| Fresh-to-live project workflow | `accelerator-project-lifecycle` |
| Remote mutation | `accelerator-deployment` |
| Read-only runtime diagnosis | `accelerator-ops-observability` |

Command `--help`, source/framework registry, policy, database, and runtime state are truth. Context output and skills are navigation aids.

The complete opinionated lifecycle is stored in `resources/boost/skills/accelerator-project-lifecycle/references/workflow.md`. Efficient Nightwatch MCP issue resolution is stored in `resources/boost/skills/accelerator-nightwatch-mcp/references/issue-workflow.md`. Keep those references synchronized with public Artisan commands and deployment invariants; do not duplicate divergent workflows elsewhere.

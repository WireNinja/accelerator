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

```bash
php artisan accelerator:configure deployment
php artisan accelerator:configure deployment --migrate-legacy --force --json --no-interaction
php artisan accelerator:configure environment --stage=production
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy:init --stage=production
php artisan accelerator:deploy --stage=production
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:deploy:unlock --stage=production
php artisan accelerator:deploy:relocate --stage=production --old-root=/var/www/old.example.com
```

Configuration never SSHes. Changing a domain recalculates `/var/www/{domain}` and reports the exact relocation command; it never moves remote files implicitly. Remote mutators confirm stage, domain, root, and host; non-interactive mutation requires explicit force. Deployment uses committed Composer/pnpm/npm locks, installs rather than updates dependencies, backs up the database before migrations, switches releases atomically, restarts only configured services, and never auto-rolls back database migrations. A failed post-switch health check may restore the previous code symlink, but database review remains manual.

Shared Laravel storage and cache paths use inherited ACLs for both the deploy user and runtime user. Releases become rollback candidates only after services pass the retried HTTPS health check; incomplete or failed releases are marked bad and never selected as rollback targets.

Certbot obtains or reuses certificate material through the dedicated ACME webroot; it does not rewrite the Nginx virtual host. Accelerator remains the single owner and renderer of both HTTP and HTTPS configuration.

The deploy recipe clones the application repository and initializes only the tracked `packages/accelerator` submodule before Composer runs. It intentionally does not recurse through unrelated submodules, so a broken or optional gitlink elsewhere cannot widen deployment scope.

`deploy:init` and ordinary deploy run a read-only ownership/collision preflight before mutation. It rejects unmanaged roots, duplicate Nginx domains, duplicate Supervisor groups/programs, and occupied Octane/Reverb/Nightwatch ports. Accelerator-written roots and service files carry project/stage ownership markers, so `--force` never means “take over another project”. Dual staging/production topology uses distinct `{project}_{stage}` Supervisor groups and distinct listener ports on the same SSH host.

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
| Remote mutation | `accelerator-deployment` |
| Read-only runtime diagnosis | `accelerator-ops-observability` |

Command `--help`, source/framework registry, policy, database, and runtime state are truth. Context output and skills are navigation aids.

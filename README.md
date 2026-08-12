# WireNinja Accelerator

Opinionated Laravel 13 + Filament foundation for one solo developer shipping repeated single-VPS monoliths.

## Fixed product contract

- Filament, System settings, Shield RBAC, activity logging, auth/MFA, custom sidebar/topbar, and Indonesian defaults are core.
- Root `/` belongs to userland.
- pnpm is default; npm is fallback.
- OAuth, PWA, Telegram, centralized realtime, Scout, and OpenTelemetry observability are optional integrations.
- PHP-FPM is the only client HTTP runtime.
- Queues always use Laravel's database driver.
- Client applications own zero Supervisor programs.
- Realtime is served by centralized Reverb; telemetry is exported directly to centralized OpenObserve.

## Fresh installation

```bash
laravel new project
cd project
composer require wireninja/accelerator
php artisan accelerator:install
```

The installer is fresh-only and resumable. Existing applications must use a surgical migration; never run the installer because it may execute `migrate:fresh --seed`.

Local development:

```bash
composer dev
```

This runs Laravel, `schedule:work`, Pail, and Vite. The package-owned schedule drains the database queue every 10 seconds with a bounded `queue:work --stop-when-empty` process.

## Runtime

```text
Nginx -> shared PHP 8.5 FPM -> Laravel

/etc/cron.d/acc-{deployment_key}-{stage}
  -> every minute: php artisan schedule:run
       -> every 10 seconds: bounded database queue drain

Laravel broadcasting -> centralized-reverb.ohmyserver.com
Laravel OTLP/HTTP     -> observe.ohmyserver.com
```

There is no client Octane, Horizon, local Reverb server, Nightwatch, NightOwl, OpenTelemetry Collector, telemetry PostgreSQL database, or Supervisor program.

## Configuration ownership

| File | Purpose |
|---|---|
| `.env` | Local runtime and local secrets. |
| `.env.example` | Public key contract; never credentials. |
| `.accelerator/deploy.json` | Committed schema-3 deployment topology. |
| `.accelerator/environments/{stage}.env` | Ignored canonical stage runtime and secrets. |

```bash
php artisan accelerator:configure application
php artisan accelerator:configure features
php artisan accelerator:configure deployment
php artisan accelerator:configure environment --stage=staging
php artisan accelerator:env:validate --stage=staging --json
```

Deployment schema 3 keeps `deployment_key` stable and domains mutable. It contains no port block, HTTP runtime selector, worker count, or process topology.

## Deployment

All normal operations originate from the local machine through Artisan. Deployer, SSH, Nginx, Certbot, PHP-FPM, and cron are internal implementation details.

```bash
php artisan accelerator:deploy:preflight --stage=staging --json
php artisan accelerator:deploy:init --stage=staging --revision=<full-sha>
php artisan accelerator:deploy --stage=staging
php artisan accelerator:deploy:promote --from=staging --to=production
php artisan accelerator:deploy:status --stage=production --json
php artisan accelerator:deploy:rollback --stage=production
php artisan accelerator:env:push --stage=production
php artisan accelerator:logs laravel --stage=production
php artisan accelerator:logs scheduler --stage=production
```

Stable root is `/var/www/{domain}` with Deployer `releases`, `shared`, and `current`. Deploy reloads the exact PHP-FPM service, replaces the exact stage cron file, and removes only an old Accelerator-owned Supervisor group for the same deployment key and stage. It never uninstalls Supervisor or touches unrelated projects.

Dual stages run identical code and features. They isolate database, uploads, APP_KEY, sessions/cache namespace, OTLP credential and identity, backups, domain, and cron entry. Reverb deliberately uses one shared hub credential. Production promotion deploys the exact successful staging revision. A code rollback never reverses database migrations.

## Centralized Reverb

All client applications and stages reuse the single app ID, key, and secret from the centralized Reverb server's ignored `.env`. Their public host is `centralized-reverb.ohmyserver.com:443`. The hub `.env` survives its Git-based deploy because it is ignored; clients do not bind a Reverb port and Nginx does not proxy websocket routes locally.

## OpenObserve

Accelerator uses `keepsuit/laravel-opentelemetry` and OTLP/HTTP protobuf.

Required stage values:

```dotenv
ACCELERATOR_FEATURE_OBSERVABILITY=true
LOG_STACK=daily,otlp
OTEL_SDK_DISABLED=false
OTEL_SERVICE_NAME=deployment-key
OTEL_SERVICE_INSTANCE_ID=deployment-key-stage
OTEL_EXPORTER_OTLP_ENDPOINT=https://observe.ohmyserver.com/api/default
OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic <ingestion-only-token>,stream-name=default
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_TRACES_SAMPLER_TYPE=traceidratio
OTEL_TRACES_SAMPLER_TRACEIDRATIO_RATIO=1.0
OTEL_INSTRUMENTATION_HTTP_SERVER=false
```

`OTEL_INSTRUMENTATION_HTTP_SERVER=false` is mandatory: Accelerator starts HTTP telemetry only after session authentication resolves. Guest requests and guest logs are excluded from OTLP. Authenticated requests, CLI/scheduled work, and queue jobs remain observable. OpenObserve retains telemetry for 60 days. Never commit or print OTLP headers.

## Backups

Backups remain stage-owned, support local plus optional S3-compatible storage, verify checksums, notify through operator Telegram, and require an exact backup ID for restore.

```bash
php artisan accelerator:backup --stage=production --only=all
php artisan accelerator:backup:status --stage=production --json
php artisan accelerator:backup:verify --stage=production --backup=<id> --json
php artisan accelerator:backup:restore --stage=production --backup=<id> --only=all
```

## AI routing

Boost resources are canonical:

| Task | Skill |
|---|---|
| Fresh install | `accelerator-installation` |
| Existing app migration | `accelerator-breaking-changes` |
| Full lifecycle | `accelerator-project-lifecycle` |
| Env/topology | `accelerator-env-config` |
| Server mutation | `accelerator-deployment` |
| Read-only diagnosis | `accelerator-ops-observability` |
| OpenTelemetry/OpenObserve | `accelerator-observability` |
| Business feature | `accelerator-feature-development` |
| Filament/RBAC | `accelerator-filament` |

Never run Composer update on the VPS, print stage env contents, infer release/tag/push authority, or mutate another project.

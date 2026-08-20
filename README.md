# WireNinja Accelerator

Opinionated Laravel 13.26.1+ and Filament foundation for one solo developer shipping repeated single-VPS monoliths.

## Fixed product contract

- Filament, System settings, Shield RBAC, activity logging, auth/MFA, custom sidebar/topbar, and Indonesian defaults are core.
- Root `/` belongs to userland.
- pnpm is default; npm is fallback.
- OAuth, PWA, Telegram, centralized realtime, Scout, and OpenTelemetry observability are optional integrations.
- PHP-FPM is the only client HTTP runtime.
- Queues always use Laravel's database driver.
- Client applications own zero Supervisor programs.
- Realtime is served by centralized Reverb; telemetry is exported directly to centralized OpenObserve.

## Installation

```bash
laravel new project
cd project
composer require wireninja/accelerator
php artisan accelerator:install
```

The installer is resumable and intentionally destructive: package-owned recipe files are overwritten and the database may be rebuilt with `migrate:fresh --seed`. A completed `.accelerator/install-state.json` receipt triggers an explicit confirmation before reinstalling; automation must opt in with `--force`. Use the surgical migration workflow only when existing application code or data must be preserved.

Local development:

```bash
php artisan dev
```

This runs Laravel, `schedule:work`, Pail, and Vite. Accelerator excludes Laravel's default queue listener because the package-owned schedule drains the database queue every 10 seconds with a bounded `queue:work --stop-when-empty` process.

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
| `.accelerator/reverb-apps.json` | Ignored registration payload for isolated centralized Reverb applications. |
| `.easyploy/manifest.json` | Committed non-secret deployment topology. |
| `.easyploy/environments/{stage}.env` | Ignored canonical stage runtime and secrets. |

```bash
php artisan accelerator:configure application
php artisan accelerator:configure features
php artisan accelerator:doctor --json
```

Accelerator configures the application only. Easyploy owns all deployment topology and stage environments.

## Deployment

All normal operations originate from the local Mac through Easyploy. Manual SSH is break-glass only.

```bash
easyploy init
easyploy config validate --stage=staging --json
easyploy doctor --stage=staging --json
easyploy reconcile --stage=staging --dry-run --json
easyploy reconcile --stage=staging --yes
easyploy deploy --stage=staging --yes
easyploy promote --from=staging --to=production --yes
easyploy status --stage=production --json
```

Stable root is `/var/www/{domain}` with Easyploy-owned `releases`, `shared`, and `current`. Easyploy owns SSH, release switching, Nginx, a dedicated FPM pool, native cron, stage env transport, backups orchestration, and recovery. Accelerator contains no Deployer integration or public remote-operation commands.

The shared runtime `.env` remains owned by the SSH deploy user, uses the application runtime user's primary group, and has mode `0640`. This keeps secrets private while allowing PHP-FPM and native cron to load the same configuration.

Dual stages run identical code and features. They isolate database, uploads, APP_KEY, sessions/cache namespace, Reverb credentials, OTLP credential and identity, backups, domain, and cron entry. Production promotion deploys the exact successful staging revision. A code rollback never reverses database migrations.

## Centralized Reverb

Every `{deployment_key}-{stage}` uses its own app ID, key, secret, and allowed origin on the same centralized Reverb daemon. Accelerator writes the ignored `.accelerator/reverb-apps.json` registration payload; import it into the centralized server, clear its config cache, and restart Reverb before connecting the client. Clients do not bind a Reverb port and Nginx does not proxy websocket routes locally.

Rotate local credentials with `accelerator:configure features --rotate-reverb-app`. Stage credentials live in Easyploy's ignored environment files and are changed through `easyploy env edit|push`.

## OpenObserve

Accelerator uses `keepsuit/laravel-opentelemetry` and OTLP/HTTP protobuf.

Required stage values:

```dotenv
ACCELERATOR_FEATURE_OBSERVABILITY=true
LOG_STACK=daily,otlp
OTEL_SDK_DISABLED=false
OTEL_SERVICE_NAME=deployment-key-stage
OTEL_SERVICE_INSTANCE_ID=deployment-key-stage
OTEL_EXPORTER_OTLP_ENDPOINT=https://observe.ohmyserver.com/api/default
OTEL_EXPORTER_OTLP_HEADERS=Authorization=Basic <ingestion-only-token>,stream-name=default
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_TRACES_SAMPLER_TYPE=traceidratio
OTEL_TRACES_SAMPLER_TRACEIDRATIO_RATIO=1.0
OTEL_INSTRUMENTATION_HTTP_SERVER=false
```

`OTEL_INSTRUMENTATION_HTTP_SERVER=false` is mandatory: Accelerator starts HTTP telemetry only after session authentication resolves. Guest requests and guest logs are excluded from OTLP. Authenticated requests, CLI/scheduled work, and queue jobs remain observable. OpenObserve retains telemetry for 60 days. Never commit or print OTLP headers.

## Queue timeouts

The scheduled database worker raises `retry_after` above its configured timeout and derives the overlap-lock lifetime from the longest worker bound. The default worker timeout remains configurable through `ACCELERATOR_QUEUE_WORKER_TIMEOUT`. Individual jobs may use Laravel's `#[Timeout(...)]`, but that value must not exceed the worker timeout unless the worker configuration is raised too.

## Backups

Backups remain stage-owned, support local plus optional S3-compatible storage, verify checksums, notify through operator Telegram, and require an exact backup ID for restore.

```bash
easyploy backup create --stage=production --only=all --yes
easyploy backup status --stage=production --json
easyploy backup download --stage=production --backup=<id> --json
easyploy backup restore --stage=production --backup=<id> --only=all --yes
```

## AI routing

Boost resources are canonical:

| Task | Skill |
|---|---|
| Fresh install | `accelerator-installation` |
| Existing app migration | `accelerator-breaking-changes` |
| Full lifecycle | `accelerator-project-lifecycle` |
| Local app config | `accelerator-env-config` |
| Deployment and server operations | `easyploy-deployment` |
| Read-only diagnosis | `accelerator-ops-observability` |
| OpenTelemetry/OpenObserve | `accelerator-observability` |
| Business feature | `accelerator-feature-development` |
| Filament/RBAC | `accelerator-filament` |

Never run Composer update on the VPS, print stage env contents, infer release/tag/push authority, or mutate another project.

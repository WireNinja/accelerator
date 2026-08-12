# Accelerator lifecycle workflow

## Fresh application

```text
laravel new
  -> composer require wireninja/accelerator
  -> php artisan accelerator:install
  -> configure local database/features
  -> build database + models + Filament resources + Shield RBAC
  -> php artisan accelerator:doctor --json
  -> commit
```

Local `composer dev` runs the Laravel server, `schedule:work`, Pail, and Vite. The package-owned schedule drains the database queue every 10 seconds. Root `/` remains userland-owned.

## Single-stage deployment

```text
accelerator:configure deployment
  -> accelerator:configure environment --stage=production
  -> fill ignored stage secrets
  -> commit application code/topology
  -> accelerator:deploy:preflight
  -> accelerator:deploy:init
  -> HTTPS domain
```

Single-stage hides the data badge. The stage owns its database, uploads, session/cache namespace, backup namespace, Reverb client credential, OTLP ingestion credential, and cron entry.

## Dual-stage deployment

```text
configure staging + production
  -> fill two independent stage env files
  -> deploy:init staging
  -> deploy:init production
  -> normal change: deploy staging
  -> client review
  -> deploy:promote exact staging revision to production
```

Both stages run identical code and dependencies. They differ only in domain and mutable runtime data/credentials. Show `TEST DATA` and `LIVE DATA` badges.

## Runtime ownership

```text
Nginx -> PHP-FPM -> Laravel
cron each minute -> schedule:run -> bounded database queue drain every 10 seconds
Laravel broadcast client -> centralized Reverb
Laravel OTLP exporter -> centralized OpenObserve
```

No client Octane, Horizon, local Reverb server, Nightwatch, NightOwl, telemetry database, or Supervisor program exists.

## Ongoing operations

- Code/backend/frontend change: commit, deploy staging, then promote.
- Stage env change: edit/validate/diff, then explicit `accelerator:env:push`.
- Failed code release: rollback symlink; never reverse migrations automatically.
- Data incident: follow the backup-restore reference and select an exact verified archive.
- Diagnosis: status, Laravel/scheduler logs, cron, PHP-FPM, queue backlog, backup status, then central service connectivity.

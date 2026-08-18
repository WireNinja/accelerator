# Accelerator lifecycle workflow

## Fresh application

```text
laravel new
  -> composer require wireninja/accelerator
  -> php artisan accelerator:install
  -> build schema/models/Filament resources/Shield RBAC
  -> php artisan accelerator:doctor --json
  -> commit
```

Local `php artisan dev` runs Laravel, `schedule:work`, Pail, and Vite. Accelerator excludes Laravel's default queue listener because its schedule owns database queue draining. Root `/` remains userland-owned.

## Single stage

```text
easyploy init
  -> fill .easyploy/environments/production.env
  -> config validate + doctor
  -> reconcile --dry-run
  -> reconcile
  -> deploy production
  -> HTTPS domain
```

## Dual stage

```text
easyploy init with staging + production
  -> fill two independent stage env files
  -> reconcile + deploy staging
  -> verify/client review
  -> reconcile production once
  -> easyploy promote exact staging revision
```

## Ongoing operations

- Code/backend/frontend: commit, deploy staging, then promote.
- Stage env: edit/diff/push through Easyploy.
- Failed release: rollback symlink; never reverse migrations automatically.
- Data incident: choose an exact verified archive with `easyploy backup`.
- Diagnosis: Easyploy status/logs/doctor, then application and central-service checks.

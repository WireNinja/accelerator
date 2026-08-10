# NightOwl operations reference

## Identity and isolation

For stage `{stage}` and stable key `{deployment_key}`:

```text
Supervisor: acc-{deployment_key}-{stage}-nightowl
PostgreSQL role: acc_nightowl_{deployment_key}_{stage}
PostgreSQL database: acc_nightowl_{deployment_key}_{stage}
TCP ingest: stage port base + 2
UDP reservation: stage port base + 3
Health: stage port base + 4
SQLite buffer: /var/www/{domain}/shared/storage/nightowl/agent-buffer.sqlite
```

The deployment key is limited to 39 characters so the PostgreSQL identifier remains within its 63-byte limit.

## Public control plane

Run from the local project; manual SSH is break-glass only:

```bash
php artisan accelerator:deploy:preflight --stage={stage} --json
php artisan accelerator:deploy:init --stage={stage} --revision={full-git-sha}
php artisan accelerator:deploy --stage={stage} --revision={full-git-sha}
php artisan accelerator:service:status nightowl --stage={stage}
php artisan accelerator:service:restart nightowl --stage={stage}
php artisan accelerator:logs nightowl --stage={stage} --lines=200
```

`deploy:init` is idempotent for Accelerator-owned stages. It uses passwordless `sudo -u postgres` without exposing the password, creates or updates the role, creates the stage database if absent, and transfers ownership if it already exists. Ordinary deploys never create credentials or databases.

## Collection policy

Global request and exception sampling must remain zero. `SampleAuthenticatedNightOwlRequest` calls `Nightwatch::sample()` only after Laravel can resolve the authenticated user. This blocks guest requests and guest exceptions, including hostile bot traffic, while preserving the complete authenticated trace.

Set all Nightwatch ignore flags to false, command and scheduled-task rates to `1.0`, and `Nightwatch::captureDefaultVendorCommands()`. The single env override intended for routine volume tuning is:

```text
NIGHTOWL_AUTHENTICATED_REQUEST_SAMPLE_RATE=1.0
```

## Incident checks

1. Confirm the exact stage release and `acc-{key}-{stage}` Supervisor group.
2. Confirm the `nightowl` program is running and inspect its log.
3. Probe loopback health at `http://127.0.0.1:{NIGHTOWL_HEALTH_PORT}/status` from the VPS.
4. Confirm the deterministic PostgreSQL database exists and the configured role can connect, without printing credentials.
5. Run `php artisan nightowl:migrate --no-interaction` through a normal deploy when schema drift is reported.
6. Treat Grafana as a consumer outside Accelerator. Diagnose or mutate it only with explicit user scope.

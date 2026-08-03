# Accelerator end-to-end workflow

## 1. Fresh application

```text
laravel new
    |
    v
Composer require Accelerator
    |
    v
accelerator:install
    |
    +--> scaffold
    +--> local/stage env contracts
    +--> Composer + frontend locks
    +--> migrations + seed + Super Admin + Shield
    +--> frontend build + static quality gates
    |
    v
local application ready; root / remains userland-owned
```

```bash
laravel new my-project --no-interaction
cd my-project
composer require wireninja/accelerator:^2.0@dev -W --no-interaction
php artisan accelerator:install
```

The installer is fresh-only, resumable, and may run `migrate:fresh --seed`. Never install a starter kit first and never run it on an existing application.

## 2. Configuration authorities

```text
.env                                      local runtime
.env.example                              public env contract
.accelerator/deploy.json                  committed non-secret topology
.accelerator/environments/staging.env     ignored staging runtime/secrets
.accelerator/environments/production.env  ignored production runtime/secrets
.accelerator/install-state.json           ignored resume receipt only
```

Use:

```bash
php artisan accelerator:configure application
php artisan accelerator:configure features
php artisan accelerator:configure deployment
php artisan accelerator:configure environment --stage={stage}
php artisan accelerator:env:validate --stage={stage}
```

Configuration is local-only and never SSHes. Commit `deploy.json`; never commit stage env files.

Schema 2 has one stable `deployment_key` and one `port_base`. Derive Supervisor groups as `acc-{deployment_key}-{stage}`. Reserve 20 ports: staging uses offsets 0-9, production offsets 10-19, and single-stage production uses offsets 0-9. Octane/Reverb/Nightwatch use offsets 0/1/2.

```bash
php artisan accelerator:ports --host={ssh-alias} --range=9000-9999 --available=20
```

## 3. Single-stage topology

Use direct production for cheap, small, non-critical applications.

```text
one repository/branch
        |
        v
production.env
        |
        v
/var/www/{domain}
        |
        +--> releases/N
        +--> shared/.env + storage
        +--> current -> successful release
        |
        v
one Nginx vhost + one Supervisor stage group
        |
        v
production domain
```

Only production is configured. Keep `ACCELERATOR_ENVIRONMENT_INDICATOR_ENABLED=false` locally and remotely.

```bash
php artisan accelerator:configure environment --stage=production
php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:init --stage=production --revision=<full-commit-sha>
php artisan accelerator:deploy:status --stage=production --json
```

## 4. Dual-stage topology

Use staging for developer/client acceptance before production promotion.

```text
                         one repository and feature set
                                      |
                       +--------------+--------------+
                       |                             |
                       v                             v
                    staging                      production
                  TEST DATA                      LIVE DATA
                       |                             |
          /var/www/{staging-domain}       /var/www/{production-domain}
                       |                             |
          independent DB/uploads/cache    independent DB/uploads/cache
          queues/sessions/Redis prefixes  queues/sessions/Redis prefixes
          keys/Reverb/logs/backups        keys/Reverb/logs/backups
          Supervisor group/ports          Supervisor group/ports
```

Local also displays `LOCAL DATA`. Staging and production must share code, dependencies, features, UI, and behavior. Isolate all mutable state even when both stages use one VPS, SQL server, or Redis server.

```bash
php artisan accelerator:configure environment --stage=staging
php artisan accelerator:configure environment --stage=production

php artisan accelerator:deploy:preflight --stage=staging --json
php artisan accelerator:deploy:init --stage=staging --revision=<full-commit-sha>

php artisan accelerator:deploy:preflight --stage=production --json
php artisan accelerator:deploy:init --stage=production --revision=<same-full-commit-sha>
```

Promotion loop:

```text
develop -> verify -> commit/push -> deploy staging -> developer/client approval
   ^                                                     |
   +---------------- rejected/fix -----------------------+
                                                         |
                                                      approved
                                                         |
                                                         v
                                              promote exact commit to production
```

```bash
php artisan accelerator:deploy:promote --from=staging --to=production
```

Promotion reads the successful staging release revision and deploys that immutable Git commit. Never deploy a newer branch head to production under the name “promotion”.

Do not implement stage-specific editions through env flags. Use an explicit application feature flag when rollout differences are a real business requirement.

## 5. Local control plane

Run normal operations from the development machine. Artisan is the discoverable public API; Deployer provides atomic releases, SSH transports commands, and Supervisor owns long-running processes. Manual SSH is break-glass only.

```bash
php artisan accelerator:env:edit --stage=staging
php artisan accelerator:env:diff --stage=staging --json
php artisan accelerator:env:push --stage=staging
php artisan accelerator:service:status all --stage=staging --json
php artisan accelerator:service:restart octane --stage=staging
php artisan accelerator:logs laravel --stage=staging --lines=200
php artisan accelerator:backup --stage=staging --only=all
```

The ignored local stage env is canonical. Do not routinely edit remote `.env`; a later deploy would overwrite it.

## 6. VPS prerequisite boundary

Before first deployment, ensure the SSH alias works, DNS reaches the VPS, ports 80/443 are open, the repository is remotely cloneable, and required OS software exists: PHP, Composer, Git, Node/package manager, Nginx, Supervisor, Certbot, plus the selected database/Redis services. `deploy:init` provisions one application stage; it is not a generic blank-VPS installer.

## 7. First deployment

```text
deploy:init
    |
    +--> read-only ownership/port/domain preflight
    +--> confirm exact stage, host, domain, root, group
    +--> render owned Nginx and Supervisor configuration
    +--> obtain/reuse Certbot certificate
    +--> create the initial supported local database if missing
    +--> run normal atomic deploy
    |
    v
retried HTTPS /up health check
    |
    v
mark release rollback-eligible
```

External databases and database users/roles must already exist. `deploy:init` never creates external database infrastructure or changes account passwords.

## 8. Atomic deploy internals

```text
preflight -> lock -> new release -> clone configured branch
    -> initialize packages/accelerator submodule only
    -> upload stage env -> link shared .env/storage -> writable ACL
    -> composer install from lock, --no-dev
    -> pnpm frozen install or npm ci -> frontend build
    -> Laravel optimize -> pre-migration database backup -> migrate
    -> atomic current symlink switch
    -> Supervisor reread/update + restart exact stage group
    -> retried HTTPS health check -> mark success -> clean old releases
```

Never run dependency updates remotely. Supervisor, not `artisan reload`, owns process restarts.

## 9. Failure and rollback

- Failure before the symlink switch leaves the prior `current` release serving traffic.
- Failed post-switch health may restore the previous successful code symlink and restart that stage.
- Migrations are never automatically reversed.
- Only health-checked releases marked successful are rollback candidates.

```bash
php artisan accelerator:deploy:status --stage={stage} --json
php artisan accelerator:deploy:rollback --stage={stage}
php artisan accelerator:deploy:unlock --stage={stage}
```

Use unlock only for a confirmed stale lock. Treat database recovery as a separate, explicit operation.

## 10. Ongoing deploy loop

Single stage:

```text
develop -> verify -> commit/push -> accelerator:deploy production -> status/health
```

Dual stage:

```text
develop -> verify -> commit/push -> deploy staging -> approval -> promote exact revision
```

For a domain move, update topology locally and use `accelerator:deploy:relocate`; never manually rename the stable root. Relocation preserves the old root until the owner explicitly removes it.

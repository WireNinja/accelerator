# Backup, restore, and operator alerts

Accelerator delegates archive creation, destinations, retention, and monitoring to `spatie/laravel-backup`. Public control remains local Artisan; never invoke Deployer or SSH directly during normal operation.

## Stage-owned environment

Each ignored `.accelerator/environments/{stage}.env` owns:

```dotenv
ACCELERATOR_BACKUP_ENABLED=true
ACCELERATOR_BACKUP_NAME=acc-{deployment_key}-{stage}
ACCELERATOR_BACKUP_DISKS=local
ACCELERATOR_BACKUP_TIME=02:10
ACCELERATOR_BACKUP_MAXIMUM_AGE_DAYS=2
ACCELERATOR_BACKUP_MAXIMUM_STORAGE_MEGABYTES=5000
ACCELERATOR_TELEGRAM_BOT_TOKEN=
ACCELERATOR_TELEGRAM_CHAT_ID=
ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES=false
# BACKUP_ARCHIVE_PASSWORD=
```

The `ACCELERATOR_TELEGRAM_*` pair is the stage operator channel for deployment and backup incidents. It is independent from user/profile `TELEGRAM_BOT_TOKEN`. Missing operator credentials produce a doctor warning but do not block operations. Telegram delivery is best-effort and never changes the primary operation result.

`local` is zero-configuration but does not survive total VPS loss. Add a configured S3-compatible Laravel disk to `ACCELERATOR_BACKUP_DISKS` for offsite durability. Do not store credentials in `deploy.json`.

## Public workflow

```bash
php artisan accelerator:notify:test --stage=production --json
php artisan accelerator:backup --stage=production --only=all
php artisan accelerator:backup:list --stage=production --json
php artisan accelerator:backup:status --stage=production --json
php artisan accelerator:backup:verify --stage=production --backup=<exact-id> --json
php artisan accelerator:backup:cleanup --stage=production
php artisan accelerator:backup:restore --stage=production --backup=<exact-id> --only=all
```

`--only` accepts `all`, `database`, or `files`. Restore always requires an exact ID; there is deliberately no destructive `--latest`. Non-interactive restore additionally requires `--force --no-interaction`.

Every Accelerator backup has a sidecar manifest with stage/deployment ownership, exact Git revision, content mode, non-secret database identity, destination, size, and SHA-256. Verification rejects missing manifests, bad checksums, unreadable or empty ZIPs, unsafe paths/symlinks, and missing requested components.

## Scheduling and retention

Production-runtime instances register three independent, non-overlapping daily jobs: cleanup one hour before the configured backup time, a full backup at that time, and verified health monitoring one hour afterward. Stage configuration derives a deterministic minute from its reserved port block so apps on one VPS do not all start together.

The pre-migration database backup remains mandatory during deploy. Routine successes are silent unless `ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES=true`; failures alert by default. Restore started/succeeded/failed always alert.

## Destructive restore contract

Before restore, resolve and display exact SSH host, deployment key, stage, domain, root, Supervisor group, active revision, archive revision, disk, timestamp, database, and mutable paths. Then:

1. Validate local topology and canonical stage env; run ownership/collision preflight.
2. Verify exact archive identity, checksum, components, and active-revision equality before mutation.
3. Acquire the stage deployment lock.
4. Create and verify an emergency full backup of current data; abort if it fails.
5. Alert restore started, enter maintenance, and stop only the exact stage Supervisor group.
6. Restore the stage-owned local SQLite, MySQL/MariaDB, or PostgreSQL database with native tools when requested.
7. Restore only manifest-owned mutable `storage/app` data when requested; preserve backup archives and ACLs.
8. Clear/rebuild Laravel caches without migrations, restart the exact stage, leave maintenance, and run HTTPS/service health.
9. Alert success and report the emergency backup ID.

External database hosts are not automatic restore targets. A failure after maintenance begins intentionally leaves that stage isolated; inspect the reported phase and emergency backup. Never touch another stage, domain, database, root, Nginx vhost, Supervisor group, or NightOwl database. NightOwl/Grafana are separate and are never restored here.

Code rollback and data restore are separate operations. If the archive revision differs from active code, deploy the required revision first; restore never changes code or runs migrations implicitly.

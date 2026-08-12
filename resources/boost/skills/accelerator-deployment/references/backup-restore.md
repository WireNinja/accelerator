# Backup, restore, and operator alerts

Accelerator delegates archive creation, destinations, retention, and monitoring to `spatie/laravel-backup`. Public control remains local Artisan; never invoke Deployer or SSH directly during normal operation.

## Stage-owned environment

Each ignored `.accelerator/environments/{stage}.env` owns:

```dotenv
ACCELERATOR_BACKUP_ENABLED=true
ACCELERATOR_BACKUP_NAME=acc-{deployment_key}-{stage}
ACCELERATOR_BACKUP_DISKS=local
ACCELERATOR_BACKUP_S3_ENABLED=true
ACCELERATOR_BACKUP_S3_ACCESS_KEY_ID=
ACCELERATOR_BACKUP_S3_SECRET_ACCESS_KEY=
ACCELERATOR_BACKUP_S3_REGION=auto
ACCELERATOR_BACKUP_S3_BUCKET=db-backup
ACCELERATOR_BACKUP_S3_ENDPOINT=https://ACCOUNT_ID.r2.cloudflarestorage.com
ACCELERATOR_BACKUP_S3_PREFIX=accelerator
ACCELERATOR_BACKUP_TIME=02:10
ACCELERATOR_BACKUP_MAXIMUM_AGE_DAYS=2
ACCELERATOR_BACKUP_MAXIMUM_STORAGE_MEGABYTES=5000
ACCELERATOR_TELEGRAM_BOT_TOKEN=
ACCELERATOR_TELEGRAM_CHAT_ID=
ACCELERATOR_TELEGRAM_NOTIFY_SUCCESSES=false

# BACKUP_ARCHIVE_PASSWORD=

```

The `ACCELERATOR_TELEGRAM_*` pair is the stage operator channel for deployment and backup incidents. It is independent from user/profile `TELEGRAM_BOT_TOKEN`. Missing operator credentials produce a doctor warning but do not block operations. Telegram delivery is best-effort and never changes the primary operation result.

`ACCELERATOR_BACKUP_DISKS` lists ordinary Laravel destinations and keeps `local` for fast VPS-side restores. `ACCELERATOR_BACKUP_S3_ENABLED=true` additionally activates Accelerator's private `accelerator-s3` disk, so each archive and manifest are written to both local storage and the S3-compatible destination. Fresh deployed stages default this flag to `true`; explicitly use `false` for a low-criticality project that accepts total-VPS-loss risk.

Cloudflare R2 uses the account endpoint and region `auto`. The runtime needs only an R2 S3 Access Key ID and Secret Access Key with Object Read & Write access to the selected bucket; it does not use the general Cloudflare API token. Credentials must support listing, reading, writing, and deleting objects because verification, restore, and retention cleanup use all four capabilities. Keep all values in the ignored stage env, never `deploy.json`.

R2 buckets are private by default and do not implement S3 object ACL mutation. Accelerator writes to its dedicated S3 disk with private visibility but deliberately does not call `PutObjectAcl`; do not reintroduce a post-write `setVisibility()` call for this disk.

With the default prefix, object keys are isolated as `accelerator/{deployment_key}/{stage}/acc-{deployment_key}-{stage}/...`. A single bucket can therefore safely hold many projects and both stages without collisions. Do not add `accelerator-s3` manually to `ACCELERATOR_BACKUP_DISKS`; the boolean flag owns it.

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

Before restore, resolve and display exact SSH host, deployment key, stage, domain, root, active revision, archive revision, disk, timestamp, database, and mutable paths. Then:

1. Validate local topology and canonical stage env; run ownership/collision preflight.
2. Verify exact archive identity, checksum, components, and active-revision equality before mutation.
3. Acquire the stage deployment lock.
4. Create and verify an emergency full backup of current data; abort if it fails.
5. Write a mode-`0600`, Git-ignored local maintenance-bypass receipt under `.accelerator/restore-state/`, alert restore started, enter maintenance, and prevent the exact stage cron from launching writers.
6. Restore the stage-owned local SQLite, MySQL/MariaDB, or PostgreSQL database with native tools when requested.
7. Restore only manifest-owned mutable `storage/app` data when requested; preserve backup archives and ACLs.
8. Clear/rebuild Laravel caches without migrations, restart the exact stage, leave maintenance, and run HTTPS/service health.
9. Alert success, report the emergency backup ID, and delete the local receipt. A failed restore retains the receipt for explicit recovery.

External database hosts are not automatic restore targets. A failure after maintenance begins intentionally leaves that stage isolated; inspect the reported phase and emergency backup. Never touch another stage, domain, database, root, Nginx vhost, cron file, OpenObserve, or centralized Reverb.

Code rollback and data restore are separate operations. If the archive revision differs from active code, deploy the required revision first; restore never changes code or runs migrations implicitly.

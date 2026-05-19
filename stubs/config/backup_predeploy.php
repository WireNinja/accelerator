<?php

declare(strict_types=1);

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;

/**
 * Pre-deploy database backup profile (Spatie laravel-backup).
 *
 * Used by the Envoy db-backup task. Folder/name is namespaced to `*-predeploy`
 * so retention does NOT collide with the scheduled backup pool. Notifications
 * are disabled because every deploy would otherwise spam the channel.
 *
 * Run via:
 *   php artisan backup:run --config=backup_predeploy --only-db --disable-notifications
 *
 * To restore from a pre-deploy snapshot, locate the latest zip under
 *   storage/app/private/{APP_NAME}-predeploy/
 * unzip and feed the dump to the native database client manually. There is no
 * automated restore by design.
 */
return [

    'backup' => [
        'name' => env('APP_NAME', 'laravel-backup').'-predeploy',

        'source' => [
            'files' => [
                'include' => [],
                'exclude' => [],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => null,
        'database_dump_file_timestamp_format' => null,
        'database_dump_filename_base' => 'database',
        'database_dump_file_extension' => '',

        'destination' => [
            'compression_method' => ZipArchive::CM_DEFAULT,
            'compression_level' => 9,
            'filename_prefix' => 'predeploy-',
            'disks' => [
                'local',
            ],
            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),
        'encryption' => 'default',
        'verify_backup' => false,
        'tries' => 1,
        'retry_delay' => 0,
    ],

    /*
     * Notifications disabled by design. Each deploy triggers this profile, so
     * notification spam would be high-noise and low-signal.
     */
    'notifications' => [
        'notifications' => [],
        'notifiable' => Notifiable::class,
        'mail' => [
            'to' => 'your@example.com',
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],
        'slack' => ['webhook_url' => '', 'channel' => null, 'username' => null, 'icon' => null],
        'discord' => ['webhook_url' => '', 'username' => 'Laravel Backup', 'avatar_url' => ''],
        'webhook' => ['url' => ''],
    ],

    'log_channel' => null,

    'monitor_backups' => [],

    /*
     * Aggressive retention: a pre-deploy backup is only valuable for ~24-48h
     * after the deploy it precedes. Anything older is noise.
     */
    'cleanup' => [
        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 2,
            'keep_daily_backups_for_days' => 7,
            'keep_weekly_backups_for_weeks' => 0,
            'keep_monthly_backups_for_months' => 0,
            'keep_yearly_backups_for_years' => 0,
            'delete_oldest_backups_when_using_more_megabytes_than' => 1000,
        ],

        'tries' => 1,
        'retry_delay' => 0,
    ],

];

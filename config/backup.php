<?php

use Spatie\Backup\Notifications\Notifiable;
use Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification;
use Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification;
use Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification;
use Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification;
use Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes;
use Spatie\DbDumper\Compressors\GzipCompressor;

/*
| Backups (spatie/laravel-backup). See README → "Backups" for running and
| restoring them. Credentials for remote disks (S3, …) belong in .env /
| config/filesystems.php, never here.
*/

$disks = array_values(array_filter(array_map('trim', explode(',', (string) env('BACKUP_DISKS', 'backups')))));

return [

    'backup' => [

        /* Folder name inside each destination disk. */
        'name' => env('BACKUP_NAME', env('APP_NAME', 'laravel-backup')),

        'source' => [

            'files' => [

                /*
                 * Only what cannot be rebuilt from git: user uploads (post covers
                 * on the "public" disk). The application source and, above all,
                 * .env (credentials) are deliberately not archived.
                 */
                'include' => [
                    storage_path('app/public'),
                ],

                'exclude' => [
                    storage_path('app/backups'),
                    storage_path('app/backup-temp'),
                ],

                'follow_links' => false,

                'ignore_unreadable_directories' => false,

                'relative_path' => storage_path('app'),
            ],

            /*
             * Connections to dump. MySQL/MariaDB need mysqldump, PostgreSQL
             * needs pg_dump and SQLite needs the sqlite3 binary on the server.
             */
            'databases' => [
                env('DB_CONNECTION', 'mysql'),
            ],
        ],

        'database_dump_compressor' => env('BACKUP_GZIP_DUMPS', true) ? GzipCompressor::class : null,

        'database_dump_file_timestamp_format' => null,

        'database_dump_filename_base' => 'database',

        'database_dump_file_extension' => '',

        'destination' => [

            'compression_method' => ZipArchive::CM_DEFAULT,

            'compression_level' => 9,

            'filename_prefix' => '',

            /*
             * Comma-separated BACKUP_DISKS, e.g. "backups,s3". The default
             * "backups" disk (config/filesystems.php) is local and private.
             */
            'disks' => $disks,

            'continue_on_failure' => false,
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * Set BACKUP_ARCHIVE_PASSWORD to AES-256 encrypt the archive. Strongly
         * recommended for off-site disks; keep the password somewhere other
         * than the backup itself or the archive cannot be restored.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',

        'verify_backup' => (bool) env('BACKUP_VERIFY', true),

        'tries' => 1,

        'retry_delay' => 0,
    ],

    'notifications' => [

        // Successes are not mailed, so failures stand out.
        'notifications' => [
            BackupHasFailedNotification::class => ['mail'],
            UnhealthyBackupWasFoundNotification::class => ['mail'],
            CleanupHasFailedNotification::class => ['mail'],
            BackupWasSuccessfulNotification::class => [],
            HealthyBackupWasFoundNotification::class => [],
            CleanupWasSuccessfulNotification::class => [],
        ],

        'notifiable' => Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_NOTIFICATION_EMAIL', env('ADMIN_EMAIL', 'admin@fakkroun.test')),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],

        'webhook' => [
            'url' => '',
        ],
    ],

    'log_channel' => null,

    /*
     * backup:monitor (and the admin Backups page) flag a destination as
     * unhealthy when its newest backup is too old or it uses too much space.
     */
    'monitor_backups' => [
        [
            'name' => env('BACKUP_NAME', env('APP_NAME', 'laravel-backup')),
            'disks' => $disks,
            'health_checks' => [
                MaximumAgeInDays::class => (int) env('BACKUP_MAX_AGE_DAYS', 1),
                MaximumStorageInMegabytes::class => (int) env('BACKUP_MAX_STORAGE_MB', 5000),
            ],
        ],
    ],

    'cleanup' => [

        'strategy' => DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => (int) env('BACKUP_KEEP_ALL_DAYS', 7),
            'keep_daily_backups_for_days' => (int) env('BACKUP_KEEP_DAILY_DAYS', 16),
            'keep_weekly_backups_for_weeks' => (int) env('BACKUP_KEEP_WEEKLY_WEEKS', 8),
            'keep_monthly_backups_for_months' => (int) env('BACKUP_KEEP_MONTHLY_MONTHS', 4),
            'keep_yearly_backups_for_years' => (int) env('BACKUP_KEEP_YEARLY_YEARS', 2),
            'delete_oldest_backups_when_using_more_megabytes_than' => (int) env('BACKUP_MAX_STORAGE_MB', 5000),
        ],

        'tries' => 1,

        'retry_delay' => 0,
    ],

    /*
     * Scheduling (routes/console.php): backup:run at run_at, then backup:clean
     * and backup:monitor. Set BACKUP_SCHEDULE_ENABLED=false to run backups
     * outside the Laravel scheduler.
     */
    'schedule' => [
        'enabled' => (bool) env('BACKUP_SCHEDULE_ENABLED', true),
        'run_at' => env('BACKUP_RUN_AT', '01:30'),
    ],

];

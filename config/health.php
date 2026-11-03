<?php

use Spatie\Health\Notifications\CheckFailedNotification;
use Spatie\Health\Notifications\Notifiable;
use Spatie\Health\ResultStores\CacheHealthResultStore;

/*
| Application health monitoring (spatie/laravel-health). Checks are
| registered in App\Providers\HealthServiceProvider from the "checks" section
| below, run every minute by the scheduler (`health:check`), and shown to
| administrators at /admin/health. Machine-readable results are available at
| /health.json only when HEALTH_SECRET_TOKEN is set (see README → Health).
*/

return [

    /*
     * Latest results are kept in the default cache store, so no extra table
     * is needed. Use EloquentHealthResultStore if you want a history.
     */
    'result_stores' => [
        CacheHealthResultStore::class => [
            'store' => env('HEALTH_CACHE_STORE', env('CACHE_STORE', 'database')),
        ],
    ],

    'notifications' => [
        /* Mail alerts are sent only when a recipient is configured. */
        'enabled' => (bool) env('HEALTH_NOTIFICATIONS_ENABLED', true) && filled(env('HEALTH_NOTIFICATION_EMAIL')),

        'notifications' => [
            CheckFailedNotification::class => ['mail'],
        ],

        'notifiable' => Notifiable::class,

        'throttle_notifications_for_minutes' => (int) env('HEALTH_THROTTLE_MINUTES', 60),
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        'only_on_failure' => true,

        'mail' => [
            'to' => env('HEALTH_NOTIFICATION_EMAIL', ''),

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK_URL', ''),
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],
    ],

    /* The Oh Dear endpoint is not used; /health.json covers external monitors. */
    'oh_dear_endpoint' => [
        'enabled' => false,
        'always_send_fresh_results' => false,
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),
        'url' => '/oh-dear-health-check-results',
    ],

    'horizon' => [
        'heartbeat_url' => env('HORIZON_HEARTBEAT_URL'),
    ],

    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    'theme' => 'light',

    'silence_health_queue_job' => true,

    /* /health.json answers 503 when a check fails, so monitors can alert on status alone. */
    'json_results_failure_status' => 503,

    /*
     * Bearer token for /health.json. Empty (default) disables the endpoint;
     * the package's own X-Secret-Token middleware is not used.
     */
    'secret_token' => env('HEALTH_SECRET_TOKEN'),

    /*
     * Which checks run, with their thresholds (application-level settings).
     * Queue and schedule checks rely on heartbeat commands scheduled in
     * routes/console.php, so they only make sense where the scheduler runs.
     */
    'checks' => [
        'database' => true,
        'cache' => true,
        'disk_space' => [
            'enabled' => (bool) env('HEALTH_CHECK_DISK', true),
            'warn_above_percent' => (int) env('HEALTH_DISK_WARN_PERCENT', 70),
            'fail_above_percent' => (int) env('HEALTH_DISK_FAIL_PERCENT', 90),
        ],
        'queue' => [
            'enabled' => (bool) env('HEALTH_CHECK_QUEUE', true),
            'max_minutes' => (int) env('HEALTH_QUEUE_MAX_MINUTES', 5),
        ],
        'schedule' => [
            'enabled' => (bool) env('HEALTH_CHECK_SCHEDULE', true),
            'max_minutes' => (int) env('HEALTH_SCHEDULE_MAX_MINUTES', 2),
        ],
        'backups' => (bool) env('HEALTH_CHECK_BACKUPS', true),
        /* Debug mode / environment / caching checks run in production only. */
        'production_hygiene' => true,
    ],

];

<?php

use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prunes activity-log rows older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->daily();

// Rebuilds public/sitemap.xml (see config/sitemap.php). A failed run keeps the
// previous sitemap in place.
Schedule::command('sitemap:generate')
    ->cron(config('sitemap.schedule'))
    ->withoutOverlapping();

// Application health (config/health.php): run the checks every minute and keep
// the queue/schedule heartbeats fresh for the QueueCheck and ScheduleCheck.
Schedule::command('health:check')->everyMinute()->withoutOverlapping();
Schedule::command('health:queue-check-heartbeat')->everyMinute();
Schedule::command('health:schedule-check-heartbeat')->everyMinute();

// Backups (config/backup.php): back up, prune old archives, then verify the
// destinations are healthy (failures are mailed to BACKUP_NOTIFICATION_EMAIL).
if (config('backup.schedule.enabled')) {
    $backupAt = Carbon::createFromFormat('H:i', (string) config('backup.schedule.run_at', '01:30'));

    Schedule::command('backup:run')->dailyAt($backupAt->format('H:i'))->withoutOverlapping()->onOneServer();
    Schedule::command('backup:clean')->dailyAt($backupAt->copy()->addHour()->format('H:i'))->withoutOverlapping()->onOneServer();
    Schedule::command('backup:monitor')->dailyAt($backupAt->copy()->addMinutes(90)->format('H:i'))->onOneServer();
}

// Fakkroun gameplay: advance games nobody is polling, and close stale lobbies.
Schedule::command('games:advance')->everyFiveSeconds()->withoutOverlapping();
Schedule::command('rooms:expire')->everyFiveMinutes()->withoutOverlapping();
// Guest players (nickname only, no account) are removed a week after joining.
Schedule::command('model:prune', ['--model' => [User::class]])->dailyAt('03:40')->withoutOverlapping()->onOneServer();

// Advertising: end finished campaigns and reconcile events, ledger and balances.
Schedule::command('advertising:reconcile')->hourly()->withoutOverlapping()->onOneServer();

// Analytics: store yesterday's platform figures (and re-check the two days before, in case of late games).
Schedule::command('analytics:rollup --days=3')->dailyAt('00:20')->withoutOverlapping()->onOneServer();

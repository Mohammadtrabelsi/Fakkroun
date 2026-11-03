<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Checks\BackupsCheck;
use Spatie\Health\Checks\Checks\CacheCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\QueueCheck;
use Spatie\Health\Checks\Checks\ScheduleCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use Spatie\Health\Facades\Health;

/**
 * Registers the spatie/laravel-health checks enabled in config/health.php
 * ("checks" section). Kept out of AppServiceProvider so the list of checks
 * is easy to find and extend.
 */
class HealthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Health::checks($this->checks());
    }

    /**
     * @return array<int, Check>
     */
    protected function checks(): array
    {
        $config = (array) config('health.checks', []);
        $checks = [];

        if ($config['database'] ?? true) {
            $checks[] = DatabaseCheck::new();
        }

        if ($config['cache'] ?? true) {
            $checks[] = CacheCheck::new();
        }

        if ($config['disk_space']['enabled'] ?? true) {
            $checks[] = UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage((int) ($config['disk_space']['warn_above_percent'] ?? 70))
                ->failWhenUsedSpaceIsAbovePercentage((int) ($config['disk_space']['fail_above_percent'] ?? 90));
        }

        // A sync queue runs jobs inline, so there is no worker to monitor.
        if (($config['queue']['enabled'] ?? true) && config('queue.default') !== 'sync') {
            $checks[] = QueueCheck::new()
                ->failWhenHealthJobTakesLongerThanMinutes((int) ($config['queue']['max_minutes'] ?? 5));
        }

        if ($config['schedule']['enabled'] ?? true) {
            $checks[] = ScheduleCheck::new()
                ->heartbeatMaxAgeInMinutes((int) ($config['schedule']['max_minutes'] ?? 2));
        }

        if ($config['backups'] ?? true) {
            $maxAgeDays = (int) config('backup.monitor_backups.0.health_checks.'.MaximumAgeInDays::class, 1);

            foreach ((array) config('backup.backup.destination.disks', []) as $disk) {
                $checks[] = BackupsCheck::new()
                    ->name('Backups'.ucfirst($disk))
                    ->label(__('backups.health_label', ['disk' => $disk]))
                    ->onDisk($disk)
                    ->locatedAt(config('backup.backup.name').'/*.zip')
                    ->youngestBackShouldHaveBeenMadeBefore(now()->subDays($maxAgeDays)->subHour());
            }
        }

        if (($config['production_hygiene'] ?? true) && $this->app->isProduction()) {
            $checks[] = DebugModeCheck::new();
            $checks[] = EnvironmentCheck::new()->expectEnvironment('production');
            $checks[] = OptimizedAppCheck::new();
        }

        return $checks;
    }
}

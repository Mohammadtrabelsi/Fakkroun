<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Runs `backup:run` in the background for the admin "Back up now" action.
 * Unique per mode, so repeated clicks queue a single run. Failures are also mailed by
 * laravel-backup's own BackupHasFailed notification.
 */
class RunBackupJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 1800;

    public int $uniqueFor = 1800;

    public function __construct(public bool $onlyDatabase = true) {}

    /**
     * One pending run per mode: a database-only and a full backup don't block each other.
     */
    public function uniqueId(): string
    {
        return $this->onlyDatabase ? 'db' : 'full';
    }

    public function handle(): void
    {
        $exitCode = Artisan::call('backup:run', $this->onlyDatabase ? ['--only-db' => true] : []);

        if ($exitCode !== 0) {
            throw new RuntimeException('backup:run failed: '.trim(Artisan::output()));
        }
    }
}

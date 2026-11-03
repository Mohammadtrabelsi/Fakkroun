<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Spatie\Health\Commands\RunHealthChecksCommand;
use Spatie\Health\ResultStores\ResultStore;
use Spatie\Health\ResultStores\StoredCheckResults\StoredCheckResults;

/**
 * Reads and refreshes the spatie/laravel-health results for the admin Health
 * page. (The dashboard's lightweight "System health" card is separate: see
 * SystemHealthService.)
 */
class HealthCheckService
{
    public function __construct(private readonly ResultStore $store) {}

    public function latest(): ?StoredCheckResults
    {
        return $this->store->latestResults();
    }

    /**
     * Runs every registered check now and stores the results. Notifications
     * are left to the scheduled run, so a manual refresh never mails anyone.
     */
    public function refresh(): ?StoredCheckResults
    {
        Artisan::call(RunHealthChecksCommand::class, ['--no-notification' => true]);

        return $this->latest();
    }
}

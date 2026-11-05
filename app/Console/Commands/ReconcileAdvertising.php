<?php

namespace App\Console\Commands;

use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReconcileAdvertising extends Command
{
    protected $signature = 'advertising:reconcile';

    protected $description = 'Check impressions, sponsored deliveries, ledger entries and wallet balances against each other';

    public function handle(ReconciliationService $reconciliation, CampaignService $campaigns): int
    {
        $campaigns->refreshStatuses();
        $report = $reconciliation->run();

        if (! $report['ok']) {
            Log::warning('Advertising reconciliation found discrepancies', $report);
            $this->error('Discrepancies found: see the admin advertising page.');

            return self::FAILURE;
        }

        $this->info('Advertising ledger reconciled: no discrepancies.');

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Domain\Analytics\DailyRollup;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/** Stores daily platform statistics (next step 10). Safe to re-run. */
class RollupAnalytics extends Command
{
    protected $signature = 'analytics:rollup {--days=1 : How many past days to (re)compute, ending yesterday}';

    protected $description = 'Compute daily platform statistics for past days';

    public function handle(DailyRollup $rollup): int
    {
        $days = max(1, min(366, (int) $this->option('days')));

        for ($i = $days; $i >= 1; $i--) {
            $rollup->store(Carbon::today()->subDays($i));
        }

        $this->info("Rolled up {$days} day(s).");

        return self::SUCCESS;
    }
}

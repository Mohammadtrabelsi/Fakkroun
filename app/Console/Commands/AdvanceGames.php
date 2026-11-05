<?php

namespace App\Console\Commands;

use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\LeaderboardService;
use Illuminate\Console\Command;

/**
 * Drives games forward when nobody's page is polling (next step 07): closes
 * questions past their deadline and opens the next one after the reveal.
 * GameEngine::tick() is idempotent, so overlapping drivers are harmless.
 */
class AdvanceGames extends Command
{
    protected $signature = 'games:advance';

    protected $description = 'Advance running games whose question deadline or reveal time has passed';

    public function handle(GameEngine $engine, LeaderboardService $games): int
    {
        $count = 0;

        foreach ($games->gamesDue() as $game) {
            $engine->tick($game);
            $count++;
        }

        $this->info("Advanced {$count} game(s).");

        return self::SUCCESS;
    }
}

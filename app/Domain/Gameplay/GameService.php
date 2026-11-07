<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\Game;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing Game records.
 */
class GameService
{
    /** @use ManagesModel<Game> */
    use ManagesModel;

    protected function model(): string
    {
        return Game::class;
    }
}

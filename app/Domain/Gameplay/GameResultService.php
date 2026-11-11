<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\GameResult;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing GameResult records.
 */
class GameResultService
{
    /** @use ManagesModel<GameResult> */
    use ManagesModel;

    protected function model(): string
    {
        return GameResult::class;
    }
}

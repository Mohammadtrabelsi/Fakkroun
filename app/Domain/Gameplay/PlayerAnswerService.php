<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing PlayerAnswer records.
 */
class PlayerAnswerService
{
    /** @use ManagesModel<PlayerAnswer> */
    use ManagesModel;

    protected function model(): string
    {
        return PlayerAnswer::class;
    }
}

<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\GameQuestion;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing GameQuestion records.
 */
class GameQuestionService
{
    /** @use ManagesModel<GameQuestion> */
    use ManagesModel;

    protected function model(): string
    {
        return GameQuestion::class;
    }
}

<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionChoice;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionChoice records.
 */
class QuestionChoiceService
{
    /** @use ManagesModel<QuestionChoice> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionChoice::class;
    }
}

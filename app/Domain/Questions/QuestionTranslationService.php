<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionTranslation;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionTranslation records.
 */
class QuestionTranslationService
{
    /** @use ManagesModel<QuestionTranslation> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionTranslation::class;
    }
}

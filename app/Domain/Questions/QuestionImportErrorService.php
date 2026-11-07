<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionImportError;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionImportError records.
 */
class QuestionImportErrorService
{
    /** @use ManagesModel<QuestionImportError> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionImportError::class;
    }
}

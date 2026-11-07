<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionRevision;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionRevision records.
 */
class QuestionRevisionService
{
    /** @use ManagesModel<QuestionRevision> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionRevision::class;
    }
}

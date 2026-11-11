<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionImportBatch;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionImportBatch records.
 */
class QuestionImportBatchService
{
    /** @use ManagesModel<QuestionImportBatch> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionImportBatch::class;
    }
}

<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionPoolEntry;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionPoolEntry records.
 */
class QuestionPoolEntryService
{
    /** @use ManagesModel<QuestionPoolEntry> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionPoolEntry::class;
    }
}

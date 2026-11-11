<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Models\QuestionModerationLog;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing QuestionModerationLog records.
 */
class QuestionModerationLogService
{
    /** @use ManagesModel<QuestionModerationLog> */
    use ManagesModel;

    protected function model(): string
    {
        return QuestionModerationLog::class;
    }
}

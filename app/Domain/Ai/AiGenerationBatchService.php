<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Models\AiGenerationBatch;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing AiGenerationBatch records.
 */
class AiGenerationBatchService
{
    /** @use ManagesModel<AiGenerationBatch> */
    use ManagesModel;

    protected function model(): string
    {
        return AiGenerationBatch::class;
    }
}

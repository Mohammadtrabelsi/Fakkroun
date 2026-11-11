<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Models\AiGenerationItem;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing AiGenerationItem records.
 */
class AiGenerationItemService
{
    /** @use ManagesModel<AiGenerationItem> */
    use ManagesModel;

    protected function model(): string
    {
        return AiGenerationItem::class;
    }
}

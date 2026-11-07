<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Impression;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing Impression records.
 */
class ImpressionService
{
    /** @use ManagesModel<Impression> */
    use ManagesModel;

    protected function model(): string
    {
        return Impression::class;
    }
}

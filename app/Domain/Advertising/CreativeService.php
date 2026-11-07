<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Creative;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing Creative records.
 */
class CreativeService
{
    /** @use ManagesModel<Creative> */
    use ManagesModel;

    protected function model(): string
    {
        return Creative::class;
    }
}

<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\AdClick;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing AdClick records.
 */
class AdClickService
{
    /** @use ManagesModel<AdClick> */
    use ManagesModel;

    protected function model(): string
    {
        return AdClick::class;
    }
}

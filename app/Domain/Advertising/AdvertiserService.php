<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Advertiser;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing Advertiser records.
 */
class AdvertiserService
{
    /** @use ManagesModel<Advertiser> */
    use ManagesModel;

    protected function model(): string
    {
        return Advertiser::class;
    }
}

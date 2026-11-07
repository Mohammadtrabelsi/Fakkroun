<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\SponsoredDelivery;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing SponsoredDelivery records.
 */
class SponsoredDeliveryService
{
    /** @use ManagesModel<SponsoredDelivery> */
    use ManagesModel;

    protected function model(): string
    {
        return SponsoredDelivery::class;
    }
}

<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\SponsoredRoundDelivery;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing SponsoredRoundDelivery records.
 */
class SponsoredRoundDeliveryService
{
    /** @use ManagesModel<SponsoredRoundDelivery> */
    use ManagesModel;

    protected function model(): string
    {
        return SponsoredRoundDelivery::class;
    }
}

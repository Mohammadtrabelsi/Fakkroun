<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\AdDelivery;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing AdDelivery records.
 */
class AdDeliveryService
{
    /** @use ManagesModel<AdDelivery> */
    use ManagesModel;

    protected function model(): string
    {
        return AdDelivery::class;
    }
}

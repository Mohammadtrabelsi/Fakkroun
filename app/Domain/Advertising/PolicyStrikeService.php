<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\PolicyStrike;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing PolicyStrike records.
 */
class PolicyStrikeService
{
    /** @use ManagesModel<PolicyStrike> */
    use ManagesModel;

    /** How many policy breaches are on record for this advertiser. */
    public function countFor(Advertiser $advertiser): int
    {
        return $this->query()->where('advertiser_id', $advertiser->id)->count();
    }

    protected function model(): string
    {
        return PolicyStrike::class;
    }
}

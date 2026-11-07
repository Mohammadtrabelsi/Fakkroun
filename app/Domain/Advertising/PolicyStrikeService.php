<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\PolicyStrike;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing PolicyStrike records.
 */
class PolicyStrikeService
{
    /** @use ManagesModel<PolicyStrike> */
    use ManagesModel;

    protected function model(): string
    {
        return PolicyStrike::class;
    }
}

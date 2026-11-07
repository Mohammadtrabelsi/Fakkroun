<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\LedgerEntry;
use App\Services\Concerns\ManagesModel;

/**
 * Single entry point for reading and writing LedgerEntry records.
 */
class LedgerEntryService
{
    /** @use ManagesModel<LedgerEntry> */
    use ManagesModel;

    protected function model(): string
    {
        return LedgerEntry::class;
    }
}

<?php

namespace Database\Seeders;

use App\Domain\Advertising\Models\AdvertisingPackage;
use Illuminate\Database\Seeder;

/**
 * The standard advertiser packs (Essential, Professional, Enterprise) with
 * their default values. Idempotent; never overwrites admin edits.
 */
class AdvertisingPackageSeeder extends Seeder
{
    public function run(): void
    {
        AdvertisingPackage::ensureDefaults();
    }
}

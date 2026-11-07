<?php

namespace Database\Seeders;

use App\Domain\Advertising\AdvertisingPackageService;
use Illuminate\Database\Seeder;

/**
 * The standard advertiser packs (Essential, Professional, Enterprise) with
 * their default values. Idempotent; never overwrites admin edits.
 */
class AdvertisingPackageSeeder extends Seeder
{
    public function run(): void
    {
        app(AdvertisingPackageService::class)->ensureDefaults();
    }
}

<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Services\Concerns\ManagesModel;
use App\Services\PlatformSettings;

/**
 * Single entry point for reading and writing AdvertisingPackage records.
 */
class AdvertisingPackageService
{
    /** @use ManagesModel<AdvertisingPackage> */
    use ManagesModel;

    /**
     * Creates the standard tiers that don't exist yet. Never changes a pack an
     * admin already edited.
     */
    public function ensureDefaults(): void
    {
        // Also runs inside a migration, so never fail on settings: fall back to TND.
        $currency = (string) (rescue(fn () => app(PlatformSettings::class)->get('advertising.currency'), 'TND', report: false) ?: 'TND');

        foreach (AdvertisingPackage::TIERS as $slug) {
            $values = AdvertisingPackage::defaultsFor($slug);

            if ($values !== null && ! $this->query()->where('slug', $slug)->exists()) {
                $this->create($values + ['currency' => $currency, 'is_active' => true, 'is_custom' => false]);
            }
        }
    }

    /**
     * Copies the default values back onto a standard tier (admin action).
     * Returns false for a custom pack, which has no defaults.
     */
    public function resetToDefault(AdvertisingPackage $package): bool
    {
        $values = AdvertisingPackage::defaultsFor($package->slug);

        if ($values === null) {
            return false;
        }

        unset($values['slug']);

        return $package->forceFill($values)->save();
    }

    protected function model(): string
    {
        return AdvertisingPackage::class;
    }
}

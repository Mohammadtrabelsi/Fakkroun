<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Advertiser pack (spec §19, story 16 S11). Prices are data, never code.
 * Packs belong to advertiser accounts only; player accounts never buy them.
 *
 * The standard tiers (slug essential | professional | enterprise) start from
 * config('fakkroun.advertising_packs') and are then edited by admins.
 */
class AdvertisingPackage extends Model
{
    public const TIERS = ['essential', 'professional', 'enterprise'];

    protected $fillable = ['slug', 'name', 'description', 'impression_credits', 'sponsored_question_credits', 'sponsored_round_credits', 'price_minor', 'currency', 'is_active', 'is_custom', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_custom' => 'boolean',
            'impression_credits' => 'integer',
            'sponsored_question_credits' => 'integer',
            'sponsored_round_credits' => 'integer',
            'price_minor' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<AdvertisingPackage>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('price_minor')->orderBy('id');
    }

    /**
     * Default values for a standard tier, or null for a custom pack.
     *
     * @return array<string, mixed>|null
     */
    public static function defaultsFor(?string $slug): ?array
    {
        $defaults = (array) config('fakkroun.advertising_packs', []);

        return $slug !== null && isset($defaults[$slug]) ? $defaults[$slug] + ['slug' => $slug] : null;
    }
}

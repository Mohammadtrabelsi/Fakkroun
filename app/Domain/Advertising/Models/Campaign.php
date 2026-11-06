<?php

namespace App\Domain\Advertising\Models;

use App\Domain\Advertising\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Campaign extends Model
{
    protected $table = 'advertising_campaigns';

    protected $fillable = ['advertiser_id', 'name', 'starts_at', 'ends_at', 'target_category_ids', 'target_languages', 'max_per_player_per_game', 'budget_minor', 'currency'];

    protected $attributes = ['status' => 'draft'];

    protected function casts(): array
    {
        return [
            'status' => CampaignStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'target_category_ids' => 'array',
            'target_languages' => 'array',
            'max_per_player_per_game' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Advertiser, $this>
     */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    /**
     * @return HasMany<Creative, $this>
     */
    public function creatives(): HasMany
    {
        return $this->hasMany(Creative::class, 'campaign_id');
    }

    /**
     * Running now: active and within its dates.
     *
     * @param  Builder<Campaign>  $query
     */
    public function scopeRunning(Builder $query): void
    {
        $query->where('status', CampaignStatus::Active->value)
            // A suspended advertiser's credits are frozen: nothing runs.
            ->whereHas('advertiser', fn ($q) => $q->where('status', 'active'))
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()));
    }

    public function targets(?int $categoryId, ?string $language, ?int $subcategoryId = null): bool
    {
        $categories = array_map('intval', (array) ($this->target_category_ids ?? []));
        $languages = (array) ($this->target_languages ?? []);

        return ($categories === [] || $categoryId === null || in_array($categoryId, $categories, true) || ($subcategoryId !== null && in_array($subcategoryId, $categories, true)))
            && ($languages === [] || $language === null || in_array($language, $languages, true));
    }
}

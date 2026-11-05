<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Domain\Advertising\Models\SponsoredDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Advertiser dashboard figures (spec §20) and platform advertising analytics
 * (§24.3). Confirmed impressions and sponsored deliveries only — offered but
 * unconfirmed ads are never counted as impressions.
 *
 * Filters: from, to (Y-m-d), campaign_id, category_id, language.
 */
class AdvertisingStats
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forAdvertiser(?Advertiser $advertiser, array $filters = []): array
    {
        $impressions = $this->filter(Impression::query(), $advertiser, $filters, 'displayed_at');
        $deliveries = $this->filter(SponsoredDelivery::query(), $advertiser, $filters, 'delivered_at');
        $consumption = LedgerEntry::query()
            ->where('entry_type', 'consumption')
            ->when($advertiser, fn ($q) => $q->where('advertiser_id', $advertiser->id))
            ->when(filled($filters['campaign_id'] ?? null), fn ($q) => $q->where('campaign_id', (int) $filters['campaign_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()));

        return [
            'impressions' => (clone $impressions)->count(),
            'sponsored_deliveries' => (clone $deliveries)->count(),
            'sponsored_reach' => (int) (clone $deliveries)->sum('players_count'),
            'consumed_impression_credits' => -(int) (clone $consumption)->where('credit_type', CreditType::Impression->value)->sum('quantity'),
            'consumed_sponsored_credits' => -(int) (clone $consumption)->where('credit_type', CreditType::SponsoredQuestion->value)->sum('quantity'),
            'spend_minor' => (int) (clone $consumption)->sum('amount_minor'),
            'by_campaign' => (clone $impressions)->selectRaw('campaign_id, count(*) as total')->groupBy('campaign_id')->pluck('total', 'campaign_id')->all(),
            'by_category' => (clone $impressions)->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id')->all(),
            'by_language' => (clone $impressions)->selectRaw('language, count(*) as total')->groupBy('language')->pluck('total', 'language')->all(),
            'daily' => (clone $impressions)->selectRaw('date(displayed_at) as day, count(*) as total')->groupBy(DB::raw('date(displayed_at)'))->orderBy('day')->pluck('total', 'day')->all(),
            'monthly_spend' => (clone $consumption)->get(['created_at', 'amount_minor'])->groupBy(fn ($e) => $e->created_at->format('Y-m'))->map->sum('amount_minor')->all(),
        ];
    }

    /**
     * Platform-wide figures for administrators.
     *
     * @return array<string, mixed>
     */
    public function platform(array $filters = []): array
    {
        $stats = $this->forAdvertiser(null, $filters);
        $stats['revenue_paid_minor'] = (int) LedgerEntry::query()->where('entry_type', 'payment')->sum('amount_minor');
        $stats['revenue_earned_minor'] = (int) LedgerEntry::query()->whereIn('entry_type', ['consumption', 'reversal'])->sum('amount_minor');
        $stats['revenue_by_format'] = LedgerEntry::query()->whereIn('entry_type', ['consumption', 'reversal'])->selectRaw('format, sum(amount_minor) as total')->groupBy('format')->pluck('total', 'format')->all();

        return $stats;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    private function filter(Builder $query, ?Advertiser $advertiser, array $filters, string $dateColumn): Builder
    {
        return $query
            ->when($advertiser, fn ($q) => $q->whereIn('campaign_id', $advertiser->campaigns()->select('id')))
            ->when(filled($filters['campaign_id'] ?? null), fn ($q) => $q->where('campaign_id', (int) $filters['campaign_id']))
            ->when(filled($filters['category_id'] ?? null), fn ($q) => $q->where('category_id', (int) $filters['category_id']))
            ->when(filled($filters['language'] ?? null), fn ($q) => $q->where('language', $filters['language']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where($dateColumn, '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where($dateColumn, '<=', Carbon::parse($filters['to'])->endOfDay()));
    }
}

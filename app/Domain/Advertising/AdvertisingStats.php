<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
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
    public function __construct(
        private readonly ImpressionService $impressions,
        private readonly SponsoredDeliveryService $sponsoredDeliveries,
        private readonly LedgerEntryService $ledgerEntries,
        private readonly AdDeliveryService $adDeliveries,
        private readonly AdClickService $adClicks,
        private readonly CreativeService $creatives,
        private readonly SponsoredRoundDeliveryService $sponsoredRoundDeliveries,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function forAdvertiser(?Advertiser $advertiser, array $filters = []): array
    {
        $impressions = $this->filter($this->impressions->query(), $advertiser, $filters, 'displayed_at');
        $deliveries = $this->filter($this->sponsoredDeliveries->query(), $advertiser, $filters, 'delivered_at');
        $consumption = $this->ledgerEntries->query()
            ->where('entry_type', 'consumption')
            ->when($advertiser, fn ($q) => $q->where('advertiser_id', $advertiser->id))
            ->when(filled($filters['campaign_id'] ?? null), fn ($q) => $q->where('campaign_id', (int) $filters['campaign_id']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where('created_at', '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where('created_at', '<=', Carbon::parse($filters['to'])->endOfDay()));

        $offered = $this->filter($this->adDeliveries->query(), $advertiser, $filters, 'issued_at', withTopic: false);
        $clicks = $this->filter($this->adClicks->query(), $advertiser, $filters, 'clicked_at', withTopic: false);
        $impressionCount = (clone $impressions)->count();
        $clickCount = (clone $clicks)->count();

        $perCreative = (clone $impressions)->selectRaw('creative_id, count(*) as total')->groupBy('creative_id')->pluck('total', 'creative_id');
        $clicksPerCreative = (clone $clicks)->selectRaw('creative_id, count(*) as total')->groupBy('creative_id')->pluck('total', 'creative_id');
        $headlines = $this->creatives->query()->whereIn('id', $perCreative->keys()->merge($clicksPerCreative->keys())->unique())->pluck('headline', 'id');

        return [
            // Offered = an ad was placed on the page; impressions = confirmed on screen (S9: kept apart).
            'offered' => (clone $offered)->count(),
            'impressions' => $impressionCount,
            'clicks' => $clickCount,
            'ctr' => $impressionCount > 0 ? round(100 * $clickCount / $impressionCount, 2) : null,
            'reach' => (clone $impressions)->distinct()->count('player_id'),
            'by_creative' => $headlines->map(fn ($headline, $id) => [
                'headline' => $headline,
                'impressions' => (int) ($perCreative[$id] ?? 0),
                'clicks' => (int) ($clicksPerCreative[$id] ?? 0),
                'ctr' => ($perCreative[$id] ?? 0) > 0 ? round(100 * ($clicksPerCreative[$id] ?? 0) / $perCreative[$id], 2) : null,
            ])->sortByDesc('impressions')->all(),
            'sponsored_deliveries' => (clone $deliveries)->count(),
            'sponsored_rounds' => $this->filter($this->sponsoredRoundDeliveries->query(), $advertiser, $filters, 'delivered_at')->count(),
            'sponsored_reach' => (int) (clone $deliveries)->sum('players_count'),
            'consumed_impression_credits' => -(int) (clone $consumption)->where('credit_type', CreditType::Impression->value)->sum('quantity'),
            'consumed_sponsored_credits' => -(int) (clone $consumption)->where('credit_type', CreditType::SponsoredQuestion->value)->sum('quantity'),
            'spend_minor' => (int) (clone $consumption)->sum('amount_minor'),
            'by_campaign' => (clone $impressions)->selectRaw('campaign_id, count(*) as total')->groupBy('campaign_id')->pluck('total', 'campaign_id')->all(),
            'by_category' => (clone $impressions)->selectRaw('category_id, count(*) as total')->groupBy('category_id')->pluck('total', 'category_id')->all(),
            'by_language' => (clone $impressions)->selectRaw('language, count(*) as total')->groupBy('language')->pluck('total', 'language')->all(),
            'by_placement' => (clone $impressions)->selectRaw('placement, count(*) as total')->groupBy('placement')->pluck('total', 'placement')->all(),
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
        $stats['revenue_paid_minor'] = (int) $this->ledgerEntries->query()->where('entry_type', 'payment')->sum('amount_minor');
        $stats['revenue_earned_minor'] = (int) $this->ledgerEntries->query()->whereIn('entry_type', ['consumption', 'reversal'])->sum('amount_minor');
        $stats['revenue_by_format'] = $this->ledgerEntries->query()->whereIn('entry_type', ['consumption', 'reversal'])->selectRaw('format, sum(amount_minor) as total')->groupBy('format')->pluck('total', 'format')->all();

        return $stats;
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<TModel>
     */
    private function filter(Builder $query, ?Advertiser $advertiser, array $filters, string $dateColumn, bool $withTopic = true): Builder
    {
        return $query
            ->when($advertiser, fn ($q) => $q->whereIn('campaign_id', $advertiser->campaigns()->select('id')))
            ->when(filled($filters['campaign_id'] ?? null), fn ($q) => $q->where('campaign_id', (int) $filters['campaign_id']))
            // Deliveries and clicks have no topic columns: those filters apply to impressions only.
            ->when($withTopic && filled($filters['category_id'] ?? null), fn ($q) => $q->where('category_id', (int) $filters['category_id']))
            ->when($withTopic && filled($filters['language'] ?? null), fn ($q) => $q->where('language', $filters['language']))
            ->when(filled($filters['from'] ?? null), fn ($q) => $q->where($dateColumn, '>=', Carbon::parse($filters['from'])->startOfDay()))
            ->when(filled($filters['to'] ?? null), fn ($q) => $q->where($dateColumn, '<=', Carbon::parse($filters['to'])->endOfDay()));
    }
}

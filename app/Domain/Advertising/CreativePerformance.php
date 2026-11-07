<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\AdClick;
use App\Domain\Advertising\Models\Impression;
use Illuminate\Support\Facades\Cache;

/**
 * Rotation by performance (story 16 S5): creatives with a higher confirmed
 * click-through rate are shown more often, while new creatives still get
 * their share.
 *
 * ratio = creative CTR / average CTR of the candidates, between 0 and 3.
 * A creative with fewer than advertising.performance_min_impressions
 * confirmed impressions counts as average (ratio 1): that is the
 * exploration share. When nobody has clicks yet, every ratio is 1.
 * Figures are cached for ten minutes.
 */
class CreativePerformance
{
    public const MAX_RATIO = 3.0;

    /**
     * @param  array<int, int>  $creativeIds
     * @return array<int, float> creative id => ratio
     */
    public function ratios(array $creativeIds, int $minImpressions): array
    {
        if ($creativeIds === []) {
            return [];
        }

        sort($creativeIds);
        $stats = Cache::remember('ad-performance:'.md5(implode(',', $creativeIds)), 600, fn () => [
            'impressions' => Impression::query()->whereIn('creative_id', $creativeIds)->selectRaw('creative_id, count(*) as total')->groupBy('creative_id')->pluck('total', 'creative_id')->all(),
            'clicks' => AdClick::query()->whereIn('creative_id', $creativeIds)->selectRaw('creative_id, count(*) as total')->groupBy('creative_id')->pluck('total', 'creative_id')->all(),
        ]);

        $ctr = [];

        foreach ($creativeIds as $id) {
            $shown = (int) ($stats['impressions'][$id] ?? 0);

            if ($shown >= max(1, $minImpressions)) {
                $ctr[$id] = (int) ($stats['clicks'][$id] ?? 0) / $shown;
            }
        }

        $average = $ctr === [] ? 0.0 : array_sum($ctr) / count($ctr);

        return collect($creativeIds)->mapWithKeys(fn (int $id) => [
            $id => $average > 0 && isset($ctr[$id]) ? min(self::MAX_RATIO, $ctr[$id] / $average) : 1.0,
        ])->all();
    }
}

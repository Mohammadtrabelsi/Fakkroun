<?php

namespace App\Http\Controllers;

use App\Domain\Advertising\Models\AdClick;
use App\Domain\Advertising\Models\AdDelivery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Learn more" (story 16 S2/S5): records one click per delivery, then sends
 * the player to the advertiser's reviewed https link. Only the player the
 * ad was delivered to is counted; anyone else is just redirected. Clicks are
 * never billed.
 */
class AdClickController extends Controller
{
    public function __invoke(Request $request, string $token): RedirectResponse
    {
        $delivery = AdDelivery::query()->with('creative')->where('token', $token)->first();
        $url = $delivery?->creative?->link_url;

        abort_if($delivery === null || ! is_string($url) || ! str_starts_with($url, 'https://'), 404);

        if ($request->user()?->id === $delivery->player_id) {
            AdClick::query()->insertOrIgnore([
                'delivery_id' => $delivery->id,
                'campaign_id' => $delivery->campaign_id,
                'creative_id' => $delivery->creative_id,
                'player_id' => $delivery->player_id,
                'placement' => $delivery->placement,
                'clicked_at' => now(),
            ]);
        }

        return redirect()->away($url);
    }
}

<?php

use App\Domain\Advertising\Models\AdClick;
use App\Domain\Advertising\Models\AdDelivery;
use Livewire\Component;

/**
 * "Learn more" (story 16 S2/S5): records one click per delivery, then sends
 * the player to the advertiser's reviewed https link. Only the player the
 * ad was delivered to is counted; anyone else is just redirected. Clicks are
 * never billed.
 */
new class extends Component
{
    public function mount(string $token): void
    {
        $delivery = AdDelivery::query()->with('creative')->where('token', $token)->first();
        $url = $delivery?->creative?->link_url;

        abort_if($delivery === null || ! is_string($url) || ! str_starts_with($url, 'https://'), 404);

        if (auth()->id() === $delivery->player_id) {
            AdClick::query()->insertOrIgnore([
                'delivery_id' => $delivery->id,
                'campaign_id' => $delivery->campaign_id,
                'creative_id' => $delivery->creative_id,
                'player_id' => $delivery->player_id,
                'placement' => $delivery->placement,
                'clicked_at' => now(),
            ]);
        }

        $this->redirect($url);
    }
};
?>

<div></div>

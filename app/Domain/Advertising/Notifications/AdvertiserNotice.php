<?php

namespace App\Domain\Advertising\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * In-app notice for advertisers (spec §23): campaign activated, credits low,
 * campaign exhausted, billing event.
 */
class AdvertiserNotice extends Notification
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $replace
     */
    public function __construct(public readonly string $event, public readonly array $replace = []) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'advertiser_'.$this->event,
            'title' => __('advertising.notices.'.$this->event, $this->replace),
            'message' => null,
            'url' => route('advertiser.dashboard'),
        ];
    }
}

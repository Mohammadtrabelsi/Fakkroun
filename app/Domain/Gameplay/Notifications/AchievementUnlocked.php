<?php

namespace App\Domain\Gameplay\Notifications;

use Illuminate\Notifications\Notification;

class AchievementUnlocked extends Notification
{
    public function __construct(public readonly string $key) {}

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
            'type' => 'achievement',
            'title' => __('progress.notice', ['name' => __('progress.achievements.'.$this->key.'.name')]),
            'message' => __('progress.achievements.'.$this->key.'.description'),
            'url' => route('players.show', $notifiable),
        ];
    }
}

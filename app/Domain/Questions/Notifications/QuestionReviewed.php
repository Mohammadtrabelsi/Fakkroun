<?php

namespace App\Domain\Questions\Notifications;

use App\Domain\Questions\Models\Question;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Tells a question's author it was approved, published or rejected
 * (spec §23). In-app only, to keep notification volume low.
 */
class QuestionReviewed extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Question $question,
        public readonly string $outcome,
        public readonly ?string $reason = null,
    ) {}

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
            'type' => 'question_reviewed',
            'question_uuid' => $this->question->uuid,
            'outcome' => $this->outcome,
            'reason' => $this->reason,
            'title' => __('questions.notifications.'.$this->outcome),
            'message' => mb_strimwidth($this->question->label(), 0, 120, '…'),
            'url' => route('questions.mine'),
        ];
    }
}

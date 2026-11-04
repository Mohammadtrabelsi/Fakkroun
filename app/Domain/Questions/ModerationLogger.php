<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionModerationLog;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Models\User;

class ModerationLogger
{
    /**
     * @param  array<string, mixed>|null  $meta
     */
    public function log(
        Question $question,
        string $action,
        ?User $actor = null,
        ?QuestionStatus $from = null,
        ?QuestionStatus $to = null,
        ?string $reason = null,
        ?array $meta = null,
        ?QuestionTranslation $translation = null,
    ): QuestionModerationLog {
        return QuestionModerationLog::query()->create([
            'question_id' => $question->id,
            'translation_id' => $translation?->id,
            'actor_id' => $actor?->id,
            'action' => $action,
            'from_status' => $from?->value,
            'to_status' => $to?->value,
            'reason' => $reason,
            'meta' => $meta,
        ]);
    }
}

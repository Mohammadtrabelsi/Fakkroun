<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

/**
 * Community question submissions (spec §9): permission, per-category opt-in,
 * a daily limit, quality and duplicate checks, then the moderation queue.
 * Questions are only published without review if an administrator enabled
 * submissions.auto_publish.
 */
class SubmissionService
{
    public function __construct(
        private readonly QuestionService $questions,
        private readonly ModerationService $moderation,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $data  QuestionService content shape
     */
    public function submit(User $player, array $data): Question
    {
        if (! $player->can('submit questions')) {
            throw new AuthorizationException;
        }

        $category = Category::query()->find($data['category_id'] ?? null);

        if ($category === null || ! $category->is_active || ! $category->allow_community_submissions) {
            throw ValidationException::withMessages(['category_id' => __('questions.errors.category_closed')]);
        }

        if ($this->remainingToday($player) <= 0) {
            throw new DomainActionException(__('questions.errors.daily_limit', ['limit' => $this->dailyLimit()]));
        }

        $question = $this->questions->create($data, $player, QuestionSource::Community, QuestionStatus::Pending);

        if ($this->settings->get('submissions.auto_publish')) {
            $question = $this->moderation->autoPublish($question);
        }

        return $question;
    }

    public function dailyLimit(): int
    {
        return (int) $this->settings->get('submissions.daily_limit_per_player');
    }

    public function remainingToday(User $player): int
    {
        $today = Question::withTrashed()
            ->where('creator_id', $player->id)
            ->where('source', QuestionSource::Community->value)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        return max(0, $this->dailyLimit() - $today);
    }

    /**
     * The player's own submissions, newest first, with status and feedback.
     */
    public function mine(User $player, int $perPage = 10): LengthAwarePaginator
    {
        return Question::query()
            ->where('creator_id', $player->id)
            ->where('source', QuestionSource::Community->value)
            ->with(['category', 'translations'])
            ->latest('id')
            ->paginate($perPage);
    }

    /**
     * Withdraws a submission that nobody has reviewed yet.
     */
    public function withdraw(User $player, Question $question): void
    {
        if ($question->creator_id !== $player->id) {
            throw new AuthorizationException;
        }

        if (! in_array($question->status, [QuestionStatus::Draft, QuestionStatus::Pending, QuestionStatus::Rejected], true)) {
            throw new DomainActionException(__('questions.errors.cannot_withdraw'));
        }

        $this->questions->delete($question, $player);
    }
}

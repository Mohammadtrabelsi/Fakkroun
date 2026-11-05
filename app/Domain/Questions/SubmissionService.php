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

    /** Statuses in which the author may still edit their own question. */
    public const EDITABLE = [QuestionStatus::Draft, QuestionStatus::ChangesRequested, QuestionStatus::Rejected];

    /**
     * Sends a new question, or the author's edited draft / returned question,
     * to the moderation queue.
     *
     * @param  array<string, mixed>  $data  QuestionService content shape
     */
    public function submit(User $player, array $data, ?Question $existing = null): Question
    {
        $this->assertCanSubmit($player, $data);

        if ($existing !== null) {
            $question = $this->questions->updateContent($this->editable($player, $existing), $data, $player);

            return $this->moderation->submit($question, $player);
        }

        $this->assertWithinLimit($player);
        $question = $this->questions->create($data, $player, QuestionSource::Community, QuestionStatus::Pending);

        if ($this->settings->get('submissions.auto_publish')) {
            $question = $this->moderation->autoPublish($question);
        }

        return $question;
    }

    /**
     * Saves without sending for review ("Save draft"). The same checks apply,
     * so a draft can always be sent later as it is.
     *
     * @param  array<string, mixed>  $data
     */
    public function saveDraft(User $player, array $data, ?Question $existing = null): Question
    {
        $this->assertCanSubmit($player, $data);

        if ($existing !== null) {
            return $this->questions->updateContent($this->editable($player, $existing), $data, $player);
        }

        $this->assertWithinLimit($player);

        return $this->questions->create($data, $player, QuestionSource::Community, QuestionStatus::Draft);
    }

    /**
     * The author's own question, if it may still be edited.
     */
    public function editable(User $player, Question $question): Question
    {
        if ($question->creator_id !== $player->id || $question->source !== QuestionSource::Community) {
            throw new AuthorizationException;
        }

        if (! in_array($question->status, self::EDITABLE, true)) {
            throw new DomainActionException(__('questions.errors.not_editable'));
        }

        return $question;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function assertCanSubmit(User $player, array $data): void
    {
        if (! $player->can('submit questions')) {
            throw new AuthorizationException;
        }

        $category = Category::query()->find($data['category_id'] ?? null);

        if ($category === null || ! $category->is_active || ! $category->allow_community_submissions) {
            throw ValidationException::withMessages(['category_id' => __('questions.errors.category_closed')]);
        }

        $errors = [];
        $max = (int) config('fakkroun.questions.community_body_max', 160);

        if (mb_strlen(trim((string) ($data['body'] ?? ''))) > $max) {
            $errors['body'] = __('questions.errors.community_too_long', ['max' => $max]);
        }

        if (config('fakkroun.questions.community_requires_source', true) && blank($data['reference_url'] ?? null)) {
            $errors['reference_url'] = __('questions.errors.source_required');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function assertWithinLimit(User $player): void
    {
        if ($this->remainingToday($player) <= 0) {
            throw new DomainActionException(__('questions.errors.daily_limit', ['limit' => $this->dailyLimit()]));
        }
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

        if (! in_array($question->status, [...self::EDITABLE, QuestionStatus::Pending], true)) {
            throw new DomainActionException(__('questions.errors.cannot_withdraw'));
        }

        $this->questions->delete($question, $player);
    }
}

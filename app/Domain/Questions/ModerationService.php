<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Domain\Questions\Notifications\QuestionReviewed;
use App\Enums\ValidationPolicy;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Question lifecycle and translation approval (spec §7.3, §5.4, §7.4).
 *
 * Who may do what:
 *  - review / reject: anyone who may moderate the question's category
 *    (admins, or moderators assigned to it — CategoryPolicy::moderate);
 *  - approve / publish: as above, except in categories whose validation
 *    policy is "admin", where only holders of `manage questions` may;
 *  - unpublish: holders of `manage questions` only.
 *
 * Every transition runs in a transaction with the question row locked, so
 * two moderators acting at once cannot both succeed.
 */
class ModerationService
{
    public function __construct(
        private readonly QuestionValidator $validator,
        private readonly QuestionPool $pool,
        private readonly ModerationLogger $logger,
    ) {}

    public function canModerate(User $user, Question $question): bool
    {
        $question->loadMissing('category');

        return $question->category !== null && $user->can('moderate', $question->category);
    }

    public function canApprove(User $user, Question $question): bool
    {
        if ($user->can('manage questions')) {
            return true;
        }

        return $question->category?->validation_policy !== ValidationPolicy::Admin
            && $this->canModerate($user, $question);
    }

    /**
     * Author (or admin) sends a draft or rejected question for review.
     */
    public function submit(Question $question, User $actor): Question
    {
        if ($question->creator_id !== $actor->id && ! $actor->can('manage questions')) {
            throw new AuthorizationException;
        }

        return $this->transition($question, $actor, [QuestionStatus::Draft, QuestionStatus::Rejected], QuestionStatus::Pending, 'submitted', function (Question $q): void {
            $q->submitted_at = now();
            $q->rejection_reason = null;
        });
    }

    public function startReview(Question $question, User $actor): Question
    {
        $this->authorizeModeration($actor, $question);

        return $this->transition($question, $actor, [QuestionStatus::Pending], QuestionStatus::UnderReview, 'review_started');
    }

    /**
     * Approves the question and its origin-language text; optionally publishes.
     */
    public function approve(Question $question, User $actor, bool $publish = false): Question
    {
        if (! $this->canApprove($actor, $question)) {
            throw new AuthorizationException(__('questions.errors.cannot_approve'));
        }

        $this->validator->assertApprovable($question->reference_url, $question->reference_text, $question->category);

        $approved = $this->transition($question, $actor, [QuestionStatus::Pending, QuestionStatus::UnderReview, QuestionStatus::Draft], QuestionStatus::Approved, 'approved', function (Question $q) use ($actor): void {
            $q->reviewed_by = $actor->id;
            $q->reviewed_at = now();
            $q->rejection_reason = null;

            $q->translations()->where('language', $q->origin_language)->update([
                'status' => TranslationStatus::Approved->value,
                'validated_by' => $actor->id,
                'validated_at' => now(),
                'rejection_reason' => null,
            ]);
        });

        if ($publish) {
            return $this->publish($approved, $actor);
        }

        $this->notifyAuthor($approved, $actor, 'approved');

        return $approved;
    }

    public function reject(Question $question, User $actor, string $reason): Question
    {
        $this->authorizeModeration($actor, $question);

        if (trim($reason) === '') {
            throw new DomainActionException(__('questions.errors.reason_required'));
        }

        $rejected = $this->transition($question, $actor, [QuestionStatus::Pending, QuestionStatus::UnderReview, QuestionStatus::Approved], QuestionStatus::Rejected, 'rejected', function (Question $q) use ($actor, $reason): void {
            $q->reviewed_by = $actor->id;
            $q->reviewed_at = now();
            $q->rejection_reason = trim($reason);
        }, trim($reason));

        $this->notifyAuthor($rejected, $actor, 'rejected', trim($reason));

        return $rejected;
    }

    public function publish(Question $question, User $actor): Question
    {
        if (! $this->canApprove($actor, $question)) {
            throw new AuthorizationException(__('questions.errors.cannot_approve'));
        }

        $published = $this->transition($question, $actor, [QuestionStatus::Approved, QuestionStatus::Unpublished], QuestionStatus::Published, 'published', function (Question $q): void {
            $origin = $q->translations()->where('language', $q->origin_language)->first();

            if (! $origin?->isApproved()) {
                throw new DomainActionException(__('questions.errors.origin_not_approved'));
            }

            $q->published_at = now();
        });

        $this->notifyAuthor($published, $actor, 'published');

        return $published;
    }

    /**
     * Takes a question out of play. Past games keep their records (spec §7.3).
     */
    public function unpublish(Question $question, User $actor, ?string $reason = null): Question
    {
        if (! $actor->can('manage questions')) {
            throw new AuthorizationException;
        }

        return $this->transition($question, $actor, [QuestionStatus::Published], QuestionStatus::Unpublished, 'unpublished', function (Question $q): void {
            $q->published_at = null;
        }, $reason);
    }

    /**
     * Platform-configured auto-publication (submissions.auto_publish). No
     * human approved it, which the log records.
     */
    public function autoPublish(Question $question): Question
    {
        return $this->transition($question, null, [QuestionStatus::Pending, QuestionStatus::Draft], QuestionStatus::Published, 'auto_published', function (Question $q): void {
            $q->published_at = now();
            $q->translations()->where('language', $q->origin_language)->update([
                'status' => TranslationStatus::Approved->value,
                'validated_at' => now(),
            ]);
        });
    }

    public function approveTranslation(QuestionTranslation $translation, User $actor): QuestionTranslation
    {
        return $this->reviewTranslation($translation, $actor, TranslationStatus::Approved);
    }

    public function rejectTranslation(QuestionTranslation $translation, User $actor, string $reason): QuestionTranslation
    {
        if (trim($reason) === '') {
            throw new DomainActionException(__('questions.errors.reason_required'));
        }

        return $this->reviewTranslation($translation, $actor, TranslationStatus::Rejected, trim($reason));
    }

    private function reviewTranslation(QuestionTranslation $translation, User $actor, TranslationStatus $status, ?string $reason = null): QuestionTranslation
    {
        $question = $translation->question()->with('category')->firstOrFail();

        if ($translation->language === $question->origin_language) {
            throw new DomainActionException(__('questions.errors.origin_follows_question'));
        }

        if ($status === TranslationStatus::Approved ? ! $this->canApprove($actor, $question) : ! $this->canModerate($actor, $question)) {
            throw new AuthorizationException(__('questions.errors.cannot_approve'));
        }

        return DB::transaction(function () use ($translation, $question, $actor, $status, $reason): QuestionTranslation {
            /** @var QuestionTranslation $locked */
            $locked = QuestionTranslation::query()->whereKey($translation->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== TranslationStatus::Pending) {
                throw new DomainActionException(__('questions.errors.translation_not_pending'));
            }

            $locked->status = $status;
            $locked->validated_by = $actor->id;
            $locked->validated_at = now();
            $locked->rejection_reason = $reason;
            $locked->save();

            $this->logger->log($question, 'translation_'.$status->value, $actor, null, null, $reason, ['language' => $locked->language], $locked);
            $this->pool->sync($question);

            return $locked;
        });
    }

    /**
     * @param  array<int, QuestionStatus>  $from
     * @param  (callable(Question): void)|null  $apply
     */
    private function transition(Question $question, ?User $actor, array $from, QuestionStatus $to, string $action, ?callable $apply = null, ?string $reason = null): Question
    {
        return DB::transaction(function () use ($question, $actor, $from, $to, $action, $apply, $reason): Question {
            /** @var Question $locked */
            $locked = Question::query()->with('category')->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status;

            if (! in_array($current, $from, true)) {
                throw new DomainActionException(__('questions.errors.invalid_transition', [
                    'from' => __('questions.status.'.$current->value),
                    'to' => __('questions.status.'.$to->value),
                ]));
            }

            if ($apply !== null) {
                $apply($locked);
            }

            $locked->status = $to;
            $locked->save();

            $this->logger->log($locked, $action, $actor, $current, $to, $reason);
            $this->pool->sync($locked);

            return $locked;
        });
    }

    private function authorizeModeration(User $actor, Question $question): void
    {
        if (! $this->canModerate($actor, $question)) {
            throw new AuthorizationException;
        }
    }

    private function notifyAuthor(Question $question, User $actor, string $outcome, ?string $reason = null): void
    {
        $author = $question->author;

        // Only people who submitted their own question want to hear about it.
        if ($author === null || $author->is($actor) || $question->source !== QuestionSource::Community) {
            return;
        }

        $author->notify(new QuestionReviewed($question, $outcome, $reason));
    }
}

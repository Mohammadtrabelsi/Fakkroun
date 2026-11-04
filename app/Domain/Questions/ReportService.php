<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\ReportReason;
use App\Domain\Questions\Enums\ReportStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionReport;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Player reports of incorrect, outdated or inappropriate questions (spec §3.1).
 */
class ReportService
{
    public function __construct(
        private readonly ModerationService $moderation,
        private readonly ModerationLogger $logger,
    ) {}

    public function report(User $player, Question $question, ReportReason $reason, ?string $details = null, ?string $language = null): QuestionReport
    {
        $key = 'question-reports:'.$player->id;
        $perHour = (int) config('fakkroun.questions.reports_per_hour', 10);

        if (RateLimiter::tooManyAttempts($key, $perHour)) {
            throw new DomainActionException(__('questions.errors.too_many_reports'));
        }

        $alreadyOpen = QuestionReport::query()
            ->where('question_id', $question->id)
            ->where('reporter_id', $player->id)
            ->where('status', ReportStatus::Open->value)
            ->exists();

        if ($alreadyOpen) {
            throw new DomainActionException(__('questions.errors.already_reported'));
        }

        RateLimiter::hit($key, 3600);

        $report = QuestionReport::query()->create([
            'question_id' => $question->id,
            'reporter_id' => $player->id,
            'language' => $language !== null && array_key_exists($language, (array) config('app.available_locales')) ? $language : null,
            'reason' => $reason->value,
            'details' => filled($details) ? mb_substr(trim((string) $details), 0, 2000) : null,
        ]);

        $this->logger->log($question, 'reported', $player, null, null, $details, ['reason' => $reason->value, 'report_id' => $report->id]);

        return $report;
    }

    public function resolve(QuestionReport $report, User $actor, ReportStatus $outcome, ?string $note = null): QuestionReport
    {
        if ($outcome === ReportStatus::Open) {
            throw new DomainActionException('A report can only be resolved or dismissed.');
        }

        if (! $this->moderation->canModerate($actor, $report->question)) {
            throw new AuthorizationException;
        }

        $report->forceFill([
            'status' => $outcome,
            'resolved_by' => $actor->id,
            'resolved_at' => now(),
            'resolution_note' => $note,
        ])->save();

        $this->logger->log($report->question, 'report_'.$outcome->value, $actor, null, null, $note, ['report_id' => $report->id]);

        return $report;
    }

    /**
     * Open reports in the categories the moderator may review.
     *
     * @param  array<int, int>|null  $categoryIds  null = every category
     */
    public function open(?array $categoryIds, int $perPage = 15): LengthAwarePaginator
    {
        return QuestionReport::query()
            ->where('status', ReportStatus::Open->value)
            ->whereHas('question', fn ($query) => $categoryIds === null ? $query : $query->inCategories($categoryIds))
            ->with(['question.translations', 'question.category', 'reporter:id,name'])
            ->latest('id')
            ->paginate($perPage, pageName: 'reports');
    }
}

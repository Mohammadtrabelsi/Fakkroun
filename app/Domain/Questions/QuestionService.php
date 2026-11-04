<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionChoice;
use App\Domain\Questions\Models\QuestionRevision;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates questions and changes their content. Status changes live in
 * ModerationService.
 *
 * Content shape ($data):
 *   category_id, subcategory_id?, difficulty, language, body,
 *   choices: [4 strings, in display order], correct: 0-based index,
 *   explanation?, reference_url?, reference_text?
 *
 * Changing the content of a question that was already approved sends it
 * back for review (spec §7.3) and records a new revision.
 */
class QuestionService
{
    /** Statuses that must be re-validated after a content change. */
    private const REVALIDATE = [
        QuestionStatus::UnderReview,
        QuestionStatus::Approved,
        QuestionStatus::Published,
        QuestionStatus::Unpublished,
    ];

    public function __construct(
        private readonly QuestionValidator $validator,
        private readonly DuplicateDetector $duplicates,
        private readonly QuestionPool $pool,
        private readonly ModerationLogger $logger,
    ) {}

    /**
     * Quality report for content that has not been saved yet.
     *
     * @param  array<string, mixed>  $data
     * @return array{warnings: array<int, string>, exact: array<int, int>, similar: array<int, float>}
     */
    public function inspect(array $data, ?int $exceptQuestionId = null): array
    {
        $warnings = $this->validator->validateQuestion($data);

        return [
            'warnings' => $warnings,
            'exact' => $this->duplicates->exactMatches($data['language'], $this->hashOf($data), $exceptQuestionId),
            'similar' => $this->duplicates->similar($data['language'], (string) $data['body'], $exceptQuestionId),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $translations  language => body, choices, explanation?
     */
    public function create(
        array $data,
        ?User $author,
        QuestionSource $source,
        QuestionStatus $status = QuestionStatus::Draft,
        array $translations = [],
        bool $allowDuplicates = false,
        ?int $importBatchId = null,
    ): Question {
        if (! in_array($status, [QuestionStatus::Draft, QuestionStatus::Pending], true)) {
            throw new DomainActionException('New questions start as a draft or pending review.');
        }

        $report = $this->inspect($data);

        if ($report['exact'] !== [] && ! $allowDuplicates) {
            throw ValidationException::withMessages(['body' => __('questions.errors.duplicate')]);
        }

        foreach ($translations as $language => $text) {
            $this->assertTranslatable($language, $data['language']);
            $this->validator->validateText($text);
        }

        return DB::transaction(function () use ($data, $author, $source, $status, $translations, $report, $importBatchId): Question {
            $question = new Question([
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'creator_id' => $author?->id,
                'origin_language' => $data['language'],
                'difficulty' => $data['difficulty'],
                'source' => $source->value,
                'reference_url' => $data['reference_url'] ?? null,
                'reference_text' => $data['reference_text'] ?? null,
                'import_batch_id' => $importBatchId,
            ]);
            // Set here, not only in a model event: seeders run with events muted.
            $question->uuid = (string) Str::uuid();
            $question->status = $status;
            $question->submitted_at = $status === QuestionStatus::Pending ? now() : null;
            $question->save();

            $choices = collect(range(1, QuestionValidator::CHOICES))
                ->map(fn (int $position) => QuestionChoice::query()->create(['question_id' => $question->id, 'position' => $position]))
                ->values();

            $question->correct_choice_id = $choices[(int) $data['correct']]->id;
            $question->save();

            $this->writeTranslation($question, $choices->all(), $data['language'], $data, $author);

            foreach ($translations as $language => $text) {
                $this->writeTranslation($question, $choices->all(), $language, $text, $author);
            }

            $this->logger->log($question, 'created', $author, null, $status, null, array_filter([
                'source' => $source->value,
                'warnings' => $report['warnings'] ?: null,
                'exact_duplicates' => $report['exact'] ?: null,
                'possible_duplicates' => $report['similar'] ?: null,
            ]));

            $this->recordRevision($question, $author, 'created');

            return $question->load(['choices', 'translations']);
        });
    }

    /**
     * Edits question metadata and the origin-language text.
     *
     * @param  array<string, mixed>  $data  same shape as create(); `language` is ignored
     */
    public function updateContent(Question $question, array $data, ?User $actor, ?string $reason = null): Question
    {
        $data['language'] = $question->origin_language;
        $this->validator->validateQuestion($data);

        if ($this->duplicates->exactMatches($question->origin_language, $this->hashOf($data), $question->id) !== []) {
            throw ValidationException::withMessages(['body' => __('questions.errors.duplicate')]);
        }

        return DB::transaction(function () use ($question, $data, $actor, $reason): Question {
            /** @var Question $locked */
            $locked = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $choices = $locked->choices()->get()->values();
            $origin = $locked->originTranslation();
            $newCorrect = $choices[(int) $data['correct']]->id;

            $locked->fill([
                'category_id' => $data['category_id'],
                'subcategory_id' => $data['subcategory_id'] ?? null,
                'difficulty' => $data['difficulty'],
                'reference_url' => $data['reference_url'] ?? null,
                'reference_text' => $data['reference_text'] ?? null,
            ]);
            $locked->correct_choice_id = $newCorrect;

            $textChanged = $origin === null || $this->textDiffers($origin, $choices->all(), $data);

            if (! $locked->isDirty() && ! $textChanged) {
                return $locked;
            }

            if ($textChanged) {
                $this->writeTranslation($locked, $choices->all(), $locked->origin_language, $data, $actor);

                // The other languages translated the old text: review them again.
                $locked->translations()
                    ->where('language', '!=', $locked->origin_language)
                    ->where('status', TranslationStatus::Approved->value)
                    ->update(['status' => TranslationStatus::Pending->value, 'validated_by' => null, 'validated_at' => null]);
            }

            $from = $locked->status;

            if (in_array($from, self::REVALIDATE, true)) {
                $locked->status = QuestionStatus::Pending;
                $locked->submitted_at = now();
                $locked->published_at = null;

                $locked->translations()
                    ->where('language', $locked->origin_language)
                    ->update(['status' => TranslationStatus::Pending->value, 'validated_by' => null, 'validated_at' => null]);
            }

            $locked->version++;
            $locked->save();

            $this->logger->log($locked, 'content_updated', $actor, $from, $locked->status, $reason, [
                'version' => $locked->version,
                'revalidation' => $from !== $locked->status,
            ]);
            $this->recordRevision($locked, $actor, $reason ?? 'content updated');
            $this->pool->sync($locked);

            return $locked->load(['choices', 'translations']);
        });
    }

    /**
     * Adds or replaces the text of a non-origin language. The translation
     * (re)enters review; the question's own status is unaffected (spec §5.4).
     *
     * @param  array<string, mixed>  $text  body, choices (4, display order), explanation?
     */
    public function upsertTranslation(Question $question, string $language, array $text, ?User $actor, bool $machineTranslated = false): QuestionTranslation
    {
        $this->assertTranslatable($language, $question->origin_language);
        $this->validator->validateText($text);

        return DB::transaction(function () use ($question, $language, $text, $actor, $machineTranslated): QuestionTranslation {
            $choices = $question->choices()->get()->values()->all();
            $existing = $question->translations()->where('language', $language)->first();

            if ($existing !== null && ! $this->textDiffers($existing, $choices, $text)) {
                return $existing;
            }

            $translation = $this->writeTranslation($question, $choices, $language, $text, $actor, $machineTranslated);

            $this->logger->log($question, $existing ? 'translation_updated' : 'translation_added', $actor, null, null, null, ['language' => $language], $translation);
            $this->pool->sync($question);

            return $translation;
        });
    }

    public function delete(Question $question, ?User $actor): void
    {
        DB::transaction(function () use ($question, $actor): void {
            $question->delete();
            $this->pool->remove($question);
            $this->logger->log($question, 'deleted', $actor, $question->status, null);
        });
    }

    public function restore(Question $question, ?User $actor): Question
    {
        $question->restore();
        $this->pool->sync($question);
        $this->logger->log($question, 'restored', $actor, null, $question->status);

        return $question;
    }

    /**
     * Full content of the current version, used for revisions and exports.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Question $question): array
    {
        $question->loadMissing(['choices', 'translations']);

        return [
            'version' => $question->version,
            'category_id' => $question->category_id,
            'subcategory_id' => $question->subcategory_id,
            'difficulty' => $question->difficulty->value,
            'origin_language' => $question->origin_language,
            'correct_choice_id' => $question->correct_choice_id,
            'choices' => $question->choices->map(fn (QuestionChoice $c) => ['id' => $c->id, 'position' => $c->position])->all(),
            'reference_url' => $question->reference_url,
            'reference_text' => $question->reference_text,
            'translations' => $question->translations->mapWithKeys(fn (QuestionTranslation $t) => [$t->language => [
                'body' => $t->body,
                'choices' => $t->choices,
                'explanation' => $t->explanation,
                'status' => $t->status->value,
            ]])->all(),
        ];
    }

    /**
     * @param  array<int, QuestionChoice>  $choices
     * @param  array<string, mixed>  $text
     */
    private function writeTranslation(Question $question, array $choices, string $language, array $text, ?User $actor, bool $machineTranslated = false): QuestionTranslation
    {
        $answers = array_values(array_map('strval', (array) $text['choices']));
        $map = [];

        foreach (array_values($choices) as $index => $choice) {
            $map[(string) $choice->id] = trim($answers[$index]);
        }

        $translation = QuestionTranslation::query()->firstOrNew(['question_id' => $question->id, 'language' => $language]);
        $translation->fill([
            'body' => trim((string) $text['body']),
            'choices' => $map,
            'explanation' => filled($text['explanation'] ?? null) ? trim((string) $text['explanation']) : null,
            'content_hash' => $this->duplicates->hash((string) $text['body'], $answers),
            'is_machine_translated' => $machineTranslated,
            'translated_by' => $actor?->id,
        ]);
        $translation->status = TranslationStatus::Pending;
        $translation->validated_by = null;
        $translation->validated_at = null;
        $translation->rejection_reason = null;
        $translation->save();

        return $translation;
    }

    /**
     * @param  array<int, QuestionChoice>  $choices
     * @param  array<string, mixed>  $text
     */
    private function textDiffers(QuestionTranslation $translation, array $choices, array $text): bool
    {
        $answers = array_values(array_map(fn ($answer) => trim((string) $answer), (array) $text['choices']));
        $current = array_map(fn (QuestionChoice $choice) => (string) ($translation->choices[(string) $choice->id] ?? ''), array_values($choices));
        $explanation = filled($text['explanation'] ?? null) ? trim((string) $text['explanation']) : null;

        return trim((string) $text['body']) !== $translation->body
            || $answers !== $current
            || $explanation !== $translation->explanation;
    }

    private function recordRevision(Question $question, ?User $actor, ?string $reason): void
    {
        QuestionRevision::query()->create([
            'question_id' => $question->id,
            'version' => $question->version,
            'snapshot' => $this->snapshot($question->fresh(['choices', 'translations'])),
            'changed_by' => $actor?->id,
            'reason' => $reason,
        ]);
    }

    private function assertTranslatable(string $language, string $originLanguage): void
    {
        if (! array_key_exists($language, (array) config('app.available_locales'))) {
            throw ValidationException::withMessages(['language' => __('questions.errors.unsupported_language')]);
        }

        if ($language === $originLanguage) {
            throw new DomainActionException(__('questions.errors.origin_is_not_a_translation'));
        }

    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function hashOf(array $data): string
    {
        return $this->duplicates->hash((string) $data['body'], array_map('strval', (array) $data['choices']));
    }
}

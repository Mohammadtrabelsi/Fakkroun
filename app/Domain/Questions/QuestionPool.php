<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;

/**
 * Keeps question_language_pool equal to "published question × approved
 * translation". Called after every change that can affect playability, so
 * the selection engine never needs to look at statuses itself, and an
 * unapproved translation can never be served (spec §5.3).
 */
class QuestionPool
{
    public function __construct(
        private readonly QuestionPoolEntryService $poolEntries,
    ) {}

    public function sync(Question $question): void
    {
        // Resolved lazily: QuestionService itself depends on the pool.
        $question = app(QuestionService::class)->withTrashed()->with('translations:id,question_id,language,status')->find($question->id);

        if ($question === null) {
            return;
        }

        $languages = ($question->trashed() || $question->status !== QuestionStatus::Published)
            ? []
            : $question->translations
                ->filter(fn ($translation) => $translation->status === TranslationStatus::Approved)
                ->pluck('language')
                ->all();

        $this->poolEntries->query()
            ->where('question_id', $question->id)
            ->when($languages !== [], fn ($query) => $query->whereNotIn('language', $languages))
            ->delete();

        foreach ($languages as $language) {
            $entry = $this->poolEntries->query()->firstOrNew([
                'question_id' => $question->id,
                'language' => $language,
            ]);

            $entry->fill([
                'category_id' => $question->category_id,
                'subcategory_id' => $question->subcategory_id,
                'difficulty' => $question->difficulty->value,
                'is_sponsored' => $question->is_sponsored,
            ]);

            // Keep an existing random key so re-syncing does not reshuffle.
            $entry->random_key ??= mt_rand() / mt_getrandmax();
            $entry->save();
        }
    }

    public function remove(Question $question): void
    {
        $this->poolEntries->query()->where('question_id', $question->id)->delete();
    }

    /**
     * @return array<int, string>
     */
    public function languages(Question $question): array
    {
        return $this->poolEntries->query()->where('question_id', $question->id)->orderBy('language')->pluck('language')->all();
    }
}

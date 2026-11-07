<?php

namespace App\Domain\Questions\Imports;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Domain\Questions\QuestionService;
use App\Services\SpreadsheetService;
use Illuminate\Support\LazyCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Exports approved and published questions with their translations and
 * metadata (spec §11), in the layout QuestionImportService reads back.
 * Rows stream lazily, so memory stays flat for any bank size.
 */
class QuestionExportService
{
    public const FORMATS = ['csv', 'xlsx', 'json'];

    public function __construct(
        private readonly SpreadsheetService $spreadsheets,
        private readonly QuestionService $questions,
    ) {}

    /**
     * @param  array<string, mixed>  $filters  category_id?, status? (approved|published)
     */
    public function download(string $format, array $filters = []): StreamedResponse
    {
        if (! in_array($format, self::FORMATS, true)) {
            abort(404);
        }

        if ($format !== 'json') {
            return $this->spreadsheets->download($this->rows($filters), 'questions', $format);
        }

        $filename = 'questions-'.now()->format('Ymd-His').'.json';

        return response()->streamDownload(function () use ($filters): void {
            echo '{"questions":[';
            $first = true;

            foreach ($this->rows($filters) as $row) {
                echo ($first ? '' : ',').json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $first = false;
            }

            echo ']}';
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function rows(array $filters = []): LazyCollection
    {
        $statuses = in_array($filters['status'] ?? null, ['approved', 'published'], true)
            ? [$filters['status']]
            : [QuestionStatus::Approved->value, QuestionStatus::Published->value];

        return $this->questions->query()
            ->whereIn('status', $statuses)
            ->when(filled($filters['category_id'] ?? null), fn ($query) => $query->inCategories([(int) $filters['category_id']]))
            ->with(['category:id,slug', 'subcategory:id,slug', 'choices', 'translations'])
            ->lazyById(200)
            ->map(fn (Question $question) => $this->row($question));
    }

    /**
     * @return array<string, mixed>
     */
    public function row(Question $question): array
    {
        $choiceIds = $question->choices->pluck('id')->values()->all();
        $origin = $question->translations->firstWhere('language', $question->origin_language);
        $correct = array_search($question->correct_choice_id, $choiceIds, true);

        $row = [
            'uuid' => $question->uuid,
            'category' => $question->category?->slug,
            'subcategory' => $question->subcategory?->slug,
            'difficulty' => $question->difficulty->value,
            'language' => $question->origin_language,
            'question' => $origin?->body,
            'choice_1' => $this->choice($origin, $choiceIds, 0),
            'choice_2' => $this->choice($origin, $choiceIds, 1),
            'choice_3' => $this->choice($origin, $choiceIds, 2),
            'choice_4' => $this->choice($origin, $choiceIds, 3),
            'correct' => $correct === false ? null : $correct + 1,
            'explanation' => $origin?->explanation,
            'reference_url' => $question->reference_url,
            'reference_text' => $question->reference_text,
            'status' => $question->status->value,
        ];

        // Fixed columns for every configured language keep spreadsheet headers stable.
        foreach (array_keys((array) config('app.available_locales')) as $language) {
            if ($language === $question->origin_language) {
                $translation = null;
            } else {
                $translation = $question->translations->firstWhere('language', $language);
            }

            $row["question_{$language}"] = $translation?->body;

            foreach (range(1, 4) as $n) {
                $row["choice_{$n}_{$language}"] = $this->choice($translation, $choiceIds, $n - 1);
            }

            $row["explanation_{$language}"] = $translation?->explanation;
            $row["status_{$language}"] = $translation?->status->value;
        }

        return $row;
    }

    /**
     * @param  array<int, int>  $choiceIds
     */
    private function choice(?QuestionTranslation $translation, array $choiceIds, int $index): ?string
    {
        return isset($choiceIds[$index]) ? $translation?->choiceText($choiceIds[$index]) : null;
    }
}

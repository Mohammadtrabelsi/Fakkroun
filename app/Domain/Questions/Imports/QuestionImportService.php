<?php

namespace App\Domain\Questions\Imports;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Jobs\ProcessQuestionImport;
use App\Domain\Questions\Models\QuestionImportBatch;
use App\Domain\Questions\Models\QuestionImportError;
use App\Domain\Questions\QuestionService;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\SimpleExcel\SimpleExcelReader;
use Throwable;

/**
 * Bulk question import from CSV, XLSX or JSON (spec §11).
 *
 * Flow: upload → read headers → map file columns to fields (suggested
 * automatically) → preview a few validated rows → start, which stores the
 * file and processes it on the queue. Every row goes through
 * QuestionService::create, so imports get the same validation and
 * duplicate checks as the admin form. Imported questions start as draft or
 * pending (imports.initial_status) and are never published by an import.
 *
 * Fields: category, subcategory, difficulty, language, question,
 * choice_1..choice_4, correct (1-4 or A-D), explanation, reference_url,
 * reference_text, and per extra language: question_<lang>,
 * choice_<n>_<lang>, explanation_<lang>. This is also the export layout.
 */
class QuestionImportService
{
    public const FORMATS = ['csv', 'xlsx', 'json'];

    public const DISK = 'local';

    /** Max error rows stored per batch; the rest are only counted. */
    public const MAX_STORED_ERRORS = 500;

    /** @var array<string, int|null> slug => id */
    private array $categoryIds = [];

    public function __construct(
        private readonly QuestionService $questions,
        private readonly PlatformSettings $settings,
    ) {}

    /**
     * Every field a column can be mapped to.
     *
     * @return array<int, string>
     */
    public function fields(): array
    {
        $fields = ['category', 'subcategory', 'difficulty', 'language', 'question', 'choice_1', 'choice_2', 'choice_3', 'choice_4', 'correct', 'explanation', 'reference_url', 'reference_text'];

        foreach (array_keys((array) config('app.available_locales')) as $language) {
            $fields[] = "question_{$language}";

            foreach (range(1, 4) as $n) {
                $fields[] = "choice_{$n}_{$language}";
            }

            $fields[] = "explanation_{$language}";
        }

        return $fields;
    }

    /**
     * @return array<int, string>
     */
    public function requiredFields(): array
    {
        return ['category', 'difficulty', 'question', 'choice_1', 'choice_2', 'choice_3', 'choice_4', 'correct'];
    }

    public function formatOf(string $filename): string
    {
        $extension = Str::lower(pathinfo($filename, PATHINFO_EXTENSION));

        if (! in_array($extension, self::FORMATS, true)) {
            throw new DomainActionException(__('questions.import.unsupported_format'));
        }

        return $extension;
    }

    /**
     * @return array<int, string>
     */
    public function headers(string $path, string $format): array
    {
        if ($format === 'json') {
            $headers = [];

            foreach ($this->jsonRows($path)->take(50) as $row) {
                $headers = array_unique([...$headers, ...array_keys($row)]);
            }

            return array_values(array_map('strval', $headers));
        }

        return array_values(array_map('strval', SimpleExcelReader::create($path, $format)->trimHeaderRow()->getHeaders() ?? []));
    }

    /**
     * Field => header, for headers whose normalised name matches a field.
     *
     * @param  array<int, string>  $headers
     * @return array<string, string>
     */
    public function suggestMapping(array $headers): array
    {
        $mapping = [];

        foreach ($headers as $header) {
            $key = $this->normalizeHeader($header);

            if (in_array($key, $this->fields(), true) && ! isset($mapping[$key])) {
                $mapping[$key] = $header;
            }
        }

        return $mapping;
    }

    /**
     * Validates the first $limit rows without saving anything.
     *
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $options  language (default origin language)
     * @return array<int, array{row: int, question: string, errors: array<int, string>, warnings: array<int, string>, duplicate: bool}>
     */
    public function preview(string $path, string $format, array $mapping, array $options = [], int $limit = 5): array
    {
        $this->assertMapping($mapping);
        $preview = [];

        foreach ($this->rows($path, $format)->take($limit) as $line => $row) {
            $item = ['row' => $line, 'question' => '', 'errors' => [], 'warnings' => [], 'duplicate' => false];

            try {
                [$data] = $this->toContent($this->mapRow($row, $mapping), $options);
                $item['question'] = (string) $data['body'];
                $report = $this->questions->inspect($data);
                $item['warnings'] = $report['warnings'];
                $item['duplicate'] = $report['exact'] !== [];
            } catch (ValidationException $e) {
                $item['errors'] = array_values($e->validator->errors()->all());
            } catch (DomainActionException $e) {
                $item['errors'] = [$e->getMessage()];
            }

            $preview[] = $item;
        }

        return $preview;
    }

    /**
     * Stores the upload and queues its processing.
     *
     * @param  array<string, string>  $mapping
     * @param  array<string, mixed>  $options
     */
    public function start(User $user, UploadedFile $file, array $mapping, array $options = []): QuestionImportBatch
    {
        $format = $this->formatOf($file->getClientOriginalName());
        $this->assertMapping($mapping);

        $path = $file->store('question-imports', self::DISK);

        $batch = QuestionImportBatch::query()->create([
            'user_id' => $user->id,
            'format' => $format,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'path' => $path,
            'column_mapping' => $mapping,
            'options' => $options,
            'status' => QuestionImportBatch::STATUS_PENDING,
        ]);

        ProcessQuestionImport::dispatch($batch->id);

        return $batch;
    }

    /**
     * Imports every row of a stored batch. Called by ProcessQuestionImport.
     */
    public function process(QuestionImportBatch $batch): QuestionImportBatch
    {
        if ($batch->isFinished()) {
            return $batch;
        }

        $batch->forceFill(['status' => QuestionImportBatch::STATUS_PROCESSING, 'started_at' => now()])->save();

        $absolute = Storage::disk(self::DISK)->path($batch->path);
        $author = $batch->user;
        $status = $this->settings->get('imports.initial_status') === 'draft' ? QuestionStatus::Draft : QuestionStatus::Pending;
        $skipDuplicates = (bool) $this->settings->get('imports.skip_duplicates');
        $maxRows = (int) config('fakkroun.questions.import_max_rows', 20000);
        $chunk = max(1, (int) config('fakkroun.questions.import_chunk_size', 100));
        $counts = ['total' => 0, 'created' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped' => 0, 'stored_errors' => 0];

        try {
            $this->rows($absolute, $batch->format)->chunk($chunk)->each(function (LazyCollection $rows) use ($batch, $author, $status, $skipDuplicates, $maxRows, &$counts): void {
                foreach ($rows as $line => $row) {
                    if ($counts['total'] >= $maxRows) {
                        $counts['skipped']++;

                        continue;
                    }

                    $counts['total']++;
                    $this->importRow($batch, $line, $row, $author, $status, $skipDuplicates, $counts);
                }

                $batch->forceFill([
                    'total_rows' => $counts['total'],
                    'created_count' => $counts['created'],
                    'duplicate_count' => $counts['duplicates'],
                    'failed_count' => $counts['failed'],
                    'skipped_count' => $counts['skipped'],
                ])->save();
            });

            $batch->forceFill(['status' => QuestionImportBatch::STATUS_COMPLETED, 'finished_at' => now()])->save();
        } catch (Throwable $e) {
            $batch->forceFill([
                'status' => QuestionImportBatch::STATUS_FAILED,
                'error' => mb_substr($e->getMessage(), 0, 1000),
                'finished_at' => now(),
            ])->save();

            throw $e;
        } finally {
            Storage::disk(self::DISK)->delete($batch->path);
        }

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $counts
     */
    private function importRow(QuestionImportBatch $batch, int $line, array $row, ?User $author, QuestionStatus $status, bool $skipDuplicates, array &$counts): void
    {
        if (array_filter($row, fn ($value) => $value !== null && $value !== '') === []) {
            $counts['total']--;

            return; // blank line
        }

        try {
            [$data, $translations] = $this->toContent($this->mapRow($row, (array) $batch->column_mapping), (array) ($batch->options ?? []));

            DB::transaction(fn () => $this->questions->create(
                $data, $author, QuestionSource::Import, $status, $translations, importBatchId: $batch->id,
            ));

            $counts['created']++;
        } catch (ValidationException $e) {
            $messages = array_values($e->validator->errors()->all());

            if ($skipDuplicates && $messages === [__('questions.errors.duplicate')]) {
                $counts['duplicates']++;

                return;
            }

            $this->fail($batch, $line, $messages, $counts);
        } catch (DomainActionException $e) {
            $this->fail($batch, $line, [$e->getMessage()], $counts);
        }
    }

    /**
     * @param  array<int, string>  $messages
     * @param  array<string, int>  $counts
     */
    private function fail(QuestionImportBatch $batch, int $line, array $messages, array &$counts): void
    {
        $counts['failed']++;

        if ($counts['stored_errors'] < self::MAX_STORED_ERRORS) {
            $counts['stored_errors']++;
            QuestionImportError::query()->create(['import_batch_id' => $batch->id, 'row_number' => $line, 'messages' => $messages]);
        }
    }

    /**
     * Rows keyed by their line in the file (header = 1 for spreadsheets,
     * array position + 1 for JSON).
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function rows(string $path, string $format): LazyCollection
    {
        if ($format === 'json') {
            return $this->jsonRows($path)->mapWithKeys(fn (array $row, int $index) => [$index + 1 => $row]);
        }

        return SimpleExcelReader::create($path, $format)
            ->trimHeaderRow()
            ->getRows()
            ->mapWithKeys(fn (array $row, int $index) => [$index + 2 => $row]);
    }

    /**
     * @return LazyCollection<int, array<string, mixed>>
     */
    private function jsonRows(string $path): LazyCollection
    {
        $decoded = json_decode((string) file_get_contents($path), true);

        if (is_array($decoded) && isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        }

        if (! is_array($decoded) || ! array_is_list($decoded)) {
            throw new DomainActionException(__('questions.import.invalid_json'));
        }

        return LazyCollection::make(function () use ($decoded) {
            foreach ($decoded as $row) {
                yield is_array($row) ? $this->flattenJsonRow($row) : [];
            }
        });
    }

    /**
     * Accepts both the flat layout and {"choices": [...]} / {"translations": {"fr": {...}}}.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function flattenJsonRow(array $row): array
    {
        if (isset($row['choices']) && is_array($row['choices'])) {
            foreach (array_values($row['choices']) as $i => $choice) {
                $row['choice_'.($i + 1)] = $choice;
            }
            unset($row['choices']);
        }

        if (isset($row['translations']) && is_array($row['translations'])) {
            foreach ($row['translations'] as $language => $text) {
                $row["question_{$language}"] = $text['question'] ?? $text['body'] ?? null;
                $row["explanation_{$language}"] = $text['explanation'] ?? null;

                foreach (array_values((array) ($text['choices'] ?? [])) as $i => $choice) {
                    $row['choice_'.($i + 1)."_{$language}"] = $choice;
                }
            }
            unset($row['translations']);
        }

        return array_map(fn ($value) => is_scalar($value) || $value === null ? $value : json_encode($value), $row);
    }

    /**
     * @param  array<string, mixed>  $row  file row
     * @param  array<string, string>  $mapping  field => header
     * @return array<string, mixed> field => value
     */
    public function mapRow(array $row, array $mapping): array
    {
        $mapped = [];

        foreach ($mapping as $field => $header) {
            if (in_array($field, $this->fields(), true) && array_key_exists($header, $row)) {
                $value = $row[$header];
                $mapped[$field] = is_string($value) ? trim($value) : $value;
                $mapped[$field] = $mapped[$field] === '' ? null : $mapped[$field];
            }
        }

        return $mapped;
    }

    /**
     * Turns a mapped row into QuestionService::create() arguments.
     *
     * @param  array<string, mixed>  $fields
     * @param  array<string, mixed>  $options
     * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
     */
    public function toContent(array $fields, array $options = []): array
    {
        $language = (string) ($fields['language'] ?? $options['language'] ?? config('app.locale'));
        $choices = [$fields['choice_1'] ?? null, $fields['choice_2'] ?? null, $fields['choice_3'] ?? null, $fields['choice_4'] ?? null];

        $data = [
            'category_id' => $this->categoryId($fields['category'] ?? null, 'category'),
            'subcategory_id' => filled($fields['subcategory'] ?? null) ? $this->categoryId($fields['subcategory'], 'subcategory') : null,
            'difficulty' => is_string($fields['difficulty'] ?? null) ? Str::lower($fields['difficulty']) : null,
            'language' => Str::lower($language),
            'body' => $fields['question'] ?? null,
            'choices' => array_map(fn ($choice) => $choice === null ? null : (string) $choice, $choices),
            'correct' => $this->correctIndex($fields['correct'] ?? null, $choices),
            'explanation' => $fields['explanation'] ?? null,
            'reference_url' => $fields['reference_url'] ?? null,
            'reference_text' => $fields['reference_text'] ?? null,
        ];

        $translations = [];

        foreach (array_keys((array) config('app.available_locales')) as $other) {
            if ($other === $data['language'] || blank($fields["question_{$other}"] ?? null)) {
                continue;
            }

            $translations[$other] = [
                'body' => $fields["question_{$other}"],
                'choices' => array_map(fn (int $n) => isset($fields["choice_{$n}_{$other}"]) ? (string) $fields["choice_{$n}_{$other}"] : null, range(1, 4)),
                'explanation' => $fields["explanation_{$other}"] ?? null,
            ];
        }

        return [$data, $translations];
    }

    /**
     * 1-4, A-D, or the exact text of one of the choices → 0-based index.
     *
     * @param  array<int, mixed>  $choices
     */
    private function correctIndex(mixed $value, array $choices): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        $value = trim((string) $value);

        if (preg_match('/^[1-4]$/', $value)) {
            return (int) $value - 1;
        }

        if (preg_match('/^[a-dA-D]$/', $value)) {
            return ord(Str::upper($value)) - ord('A');
        }

        foreach ($choices as $index => $choice) {
            if ($choice !== null && mb_strtolower(trim((string) $choice)) === mb_strtolower($value)) {
                return $index;
            }
        }

        throw ValidationException::withMessages(['correct' => __('questions.import.invalid_correct', ['value' => $value])]);
    }

    private function categoryId(mixed $slug, string $field): ?int
    {
        if (blank($slug)) {
            return null;
        }

        $slug = Str::lower(trim((string) $slug));

        if (! array_key_exists($slug, $this->categoryIds)) {
            $this->categoryIds[$slug] = Category::query()->where('slug', $slug)->value('id');
        }

        if ($this->categoryIds[$slug] === null) {
            throw ValidationException::withMessages([$field => __('questions.import.unknown_category', ['slug' => $slug])]);
        }

        return (int) $this->categoryIds[$slug];
    }

    /**
     * @param  array<string, string>  $mapping
     */
    private function assertMapping(array $mapping): void
    {
        $missing = array_diff($this->requiredFields(), array_keys(array_filter($mapping)));

        if ($missing !== []) {
            throw ValidationException::withMessages(['mapping' => __('questions.import.missing_fields', ['fields' => implode(', ', $missing)])]);
        }
    }

    private function normalizeHeader(string $header): string
    {
        // "Choice 1" → choice_1, "Reference URL" → reference_url, "question_fr" unchanged.
        return trim((string) preg_replace('/[^\pL\pN]+/u', '_', mb_strtolower(trim($header))), '_');
    }
}

<?php

namespace Database\Seeders;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Imports\QuestionImportService;
use App\Domain\Questions\QuestionService;
use App\Exceptions\DomainActionException;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Loads the question bank from database/data/questions/*.json.
 *
 * Each file uses the question import layout. Rows go through the same
 * importer path as an admin upload (QuestionImportService::toContent, then
 * QuestionService::create), so validation and duplicate checks are identical.
 * Questions are created as pending review; nothing is published here.
 *
 * Re-running is safe: exact duplicates and rows whose category is missing
 * are skipped. See docs/questions/README.md.
 */
class FakkrounQuestionBankSeeder extends Seeder
{
    /** Folder of JSON files, relative to database/. */
    public const DIRECTORY = 'data/questions';

    public function run(): void
    {
        $admin = app(UserService::class)->query()->role('admin')->first();

        if ($admin === null) {
            $this->command?->warn('No admin user: skipping question bank.');

            return;
        }

        $import = app(QuestionImportService::class);
        $questions = app(QuestionService::class);
        $created = 0;
        $skipped = 0;

        foreach ($this->files() as $path) {
            foreach ($import->rows($path, 'json') as $row) {
                try {
                    [$data, $translations] = $import->toContent(
                        $import->mapRow($row, $import->suggestMapping(array_keys($row))),
                    );

                    DB::transaction(fn () => $questions->create(
                        $data, $admin, QuestionSource::Import, QuestionStatus::Pending, $translations,
                    ));

                    $created++;
                } catch (ValidationException|DomainActionException) {
                    // Duplicate of an existing question, or unknown category slug.
                    $skipped++;
                }
            }
        }

        $this->command?->info("Question bank: {$created} created, {$skipped} skipped.");
    }

    /**
     * @return array<int, string>
     */
    public static function files(): array
    {
        $files = glob(database_path(self::DIRECTORY.'/*.json')) ?: [];
        sort($files);

        return $files;
    }
}

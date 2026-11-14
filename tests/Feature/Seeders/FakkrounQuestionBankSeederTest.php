<?php

namespace Tests\Feature\Seeders;

use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\FakkrounCategorySeeder;
use Database\Seeders\FakkrounQuestionBankSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FakkrounQuestionBankSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_json_row_is_imported_as_pending_and_rerunning_adds_nothing(): void
    {
        $this->seed([AdminUserSeeder::class, FakkrounCategorySeeder::class]);

        $expected = 0;

        foreach (FakkrounQuestionBankSeeder::files() as $path) {
            $expected += count(json_decode((string) file_get_contents($path), true));
        }

        $this->seed(FakkrounQuestionBankSeeder::class);

        $this->assertSame($expected, Question::count());
        $this->assertSame(0, Question::where('status', '!=', QuestionStatus::Pending)->count());

        $this->seed(FakkrounQuestionBankSeeder::class);

        $this->assertSame($expected, Question::count());
    }

    public function test_questions_are_stored_with_their_french_and_arabic_translations(): void
    {
        $this->seed([AdminUserSeeder::class, FakkrounCategorySeeder::class]);
        $this->seed(FakkrounQuestionBankSeeder::class);

        $question = Question::query()->with('translations')->firstOrFail();

        $this->assertEqualsCanonicalizing(
            ['en', 'fr', 'ar'],
            array_merge([$question->origin_language], $question->translations->pluck('language')->all()),
        );
    }
}

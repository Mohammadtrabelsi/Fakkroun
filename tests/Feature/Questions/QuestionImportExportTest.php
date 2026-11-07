<?php

namespace Tests\Feature\Questions;

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Imports\QuestionImportService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionImportBatch;
use App\Enums\UserRole;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;

class QuestionImportExportTest extends QuestionBankTestCase
{
    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('questions.csv', $content);
    }

    private function header(): string
    {
        return "Category,Difficulty,Question,Choice 1,Choice 2,Choice 3,Choice 4,Correct,Reference URL,question_fr,choice_1_fr,choice_2_fr,choice_3_fr,choice_4_fr\n";
    }

    public function test_headers_are_mapped_automatically(): void
    {
        $service = app(QuestionImportService::class);
        $mapping = $service->suggestMapping(['Category', 'Difficulty', 'Question', 'Choice 1', 'Reference URL', 'question_fr', 'Unrelated']);

        $this->assertSame(['category' => 'Category', 'difficulty' => 'Difficulty', 'question' => 'Question', 'choice_1' => 'Choice 1', 'reference_url' => 'Reference URL', 'question_fr' => 'question_fr'], $mapping);
    }

    public function test_csv_import_creates_pending_questions_and_reports_bad_rows(): void
    {
        Storage::fake('local');
        $slug = $this->category->slug;
        $file = $this->csv($this->header()
            ."{$slug},easy,Which gas do plants absorb from the air?,Oxygen,Carbon dioxide,Helium,Neon,2,https://example.org/a,Quel gaz les plantes absorbent-elles ?,Oxygène,Dioxyde de carbone,Hélium,Néon\n"
            ."{$slug},medium,Which planet is closest to the Sun?,Venus,Earth,Mercury,Mars,C,,,,,,\n"
            ."unknown-topic,easy,A question with an unknown category?,A,B,C,D,1,,,,,,\n"
            ."{$slug},easy,Which gas do plants absorb from the air?,Oxygen,Carbon dioxide,Helium,Neon,2,,,,,,\n");

        $service = app(QuestionImportService::class);
        $mapping = $service->suggestMapping(['Category', 'Difficulty', 'Question', 'Choice 1', 'Choice 2', 'Choice 3', 'Choice 4', 'Correct', 'Reference URL', 'question_fr', 'choice_1_fr', 'choice_2_fr', 'choice_3_fr', 'choice_4_fr']);
        $batch = $service->start($this->admin, $file, $mapping)->fresh();

        $this->assertSame(QuestionImportBatch::STATUS_COMPLETED, $batch->status);
        $this->assertSame(2, $batch->created_count);
        $this->assertSame(1, $batch->duplicate_count);
        $this->assertSame(1, $batch->failed_count);
        $this->assertSame(4, $batch->errors()->first()->row_number);

        $imported = Question::query()->where('import_batch_id', $batch->id)->get();
        $this->assertTrue($imported->every(fn (Question $q) => $q->status === QuestionStatus::Pending && $q->source === QuestionSource::Import));

        $plants = $imported->first(fn (Question $q) => str_contains($q->label(), 'plants'));
        $this->assertSame('Carbon dioxide', $plants->originTranslation()->choiceText($plants->correct_choice_id));
        $this->assertSame('Dioxyde de carbone', $plants->translation('fr')->choiceText($plants->correct_choice_id));

        $mercury = $imported->first(fn (Question $q) => str_contains($q->label(), 'closest'));
        $this->assertSame('Mercury', $mercury->originTranslation()->choiceText($mercury->correct_choice_id));

        Storage::disk('local')->assertMissing($batch->path);
    }

    public function test_preview_validates_without_saving(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'q').'.csv';
        file_put_contents($path, "category,difficulty,question,choice_1,choice_2,choice_3,choice_4,correct\n{$this->category->slug},easy,Is this a valid preview row?,Yes,No,Maybe,Never,1\n{$this->category->slug},easy,Bad row with a missing answer?,Yes,No,,Never,1\n");
        $service = app(QuestionImportService::class);

        $preview = $service->preview($path, 'csv', $service->suggestMapping($service->headers($path, 'csv')));

        $this->assertSame([], $preview[0]['errors']);
        $this->assertNotSame([], $preview[1]['errors']);
        $this->assertSame(0, Question::count());
    }

    public function test_json_import_accepts_nested_choices_and_translations(): void
    {
        Storage::fake('local');
        $json = json_encode(['questions' => [[
            'category' => $this->category->slug,
            'difficulty' => 'hard',
            'language' => 'fr',
            'question' => 'Quel est l’océan le plus profond ?',
            'choices' => ['Atlantique', 'Pacifique', 'Indien', 'Arctique'],
            'correct' => 'B',
            'translations' => ['en' => ['question' => 'Which is the deepest ocean?', 'choices' => ['Atlantic', 'Pacific', 'Indian', 'Arctic']]],
        ]]]);
        $service = app(QuestionImportService::class);
        $fields = ['category', 'difficulty', 'language', 'question', 'choice_1', 'choice_2', 'choice_3', 'choice_4', 'correct', 'question_en', 'choice_1_en', 'choice_2_en', 'choice_3_en', 'choice_4_en'];

        $batch = $service->start($this->admin, UploadedFile::fake()->createWithContent('q.json', $json), array_combine($fields, $fields))->fresh();

        $this->assertSame(1, $batch->created_count);
        $question = Question::query()->firstOrFail();
        $this->assertSame('fr', $question->origin_language);
        $this->assertSame('Pacific', $question->translation('en')->choiceText($question->correct_choice_id));
    }

    public function test_required_fields_must_be_mapped(): void
    {
        $this->expectException(ValidationException::class);

        app(QuestionImportService::class)->start($this->admin, $this->csv("question\nx\n"), ['question' => 'question']);
    }

    public function test_export_contains_published_questions_with_translations_and_reimports(): void
    {
        Question::factory()->published()
            ->withText('en', ['body' => 'Which is the largest desert in Africa?', 'choices' => ['Sahara', 'Kalahari', 'Namib', 'Libyan']])
            ->withText('fr', ['body' => 'Quel est le plus grand désert d’Afrique ?', 'choices' => ['Sahara', 'Kalahari', 'Namib', 'Libyque'], 'status' => 'approved'])
            ->create(['category_id' => $this->category->id]);
        Question::factory()->pending()->create(['category_id' => $this->category->id]);

        $component = Livewire::actingAs($this->admin)->test('pages::admin.questions.index')->call('export', 'csv');
        $path = tempnam(sys_get_temp_dir(), 'x').'.csv';
        file_put_contents($path, $this->downloadedContent($component));
        $rows = SimpleExcelReader::create($path)->getRows()->all();

        $this->assertCount(1, $rows);
        $this->assertSame('Which is the largest desert in Africa?', $rows[0]['question']);
        $this->assertSame(1, (int) $rows[0]['correct']);
        $this->assertSame('Libyque', $rows[0]['choice_4_fr']);

        Livewire::actingAs($this->admin)->test('pages::admin.questions.import')->call('export', 'json')->assertFileDownloaded();
        Livewire::actingAs($this->userWithRole(UserRole::Player))->test('pages::admin.questions.index')->assertForbidden();
    }
}

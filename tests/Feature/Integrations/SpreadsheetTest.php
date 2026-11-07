<?php

namespace Tests\Feature\Integrations;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\SpreadsheetService;
use App\Services\TagService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Tests\TestCase;

class SpreadsheetTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    /** @return array<int, array<string, string>> */
    private function csvRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, $content);

        return SimpleExcelReader::create($path)->getRows()->all();
    }

    private function csvUpload(string $content, string $name = 'categories.csv'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $content);
    }

    public function test_posts_export_streams_filtered_csv(): void
    {
        $tagged = Post::factory()->create(['title' => ['en' => 'Exported post'], 'status' => Post::STATUS_PUBLISHED, 'published_at' => now()]);
        app(TagService::class)->syncTo($tagged, ['Csv']);
        Post::factory()->create(['title' => ['en' => 'Draft post'], 'status' => Post::STATUS_DRAFT]);

        $response = $this->actingAs($this->admin)->get(route('admin.posts.export', ['format' => 'csv', 'status' => 'published']));

        $response->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('attachment; filename=posts-', $response->headers->get('Content-Disposition'));

        $rows = $this->csvRows($response->streamedContent());
        $this->assertCount(1, $rows);
        $this->assertSame('Exported post', $rows[0]['title_en']);
        $this->assertSame('Csv', $rows[0]['tags']);
        $this->assertSame($tagged->slug, $rows[0]['slug']);
    }

    public function test_xlsx_export_is_a_valid_workbook(): void
    {
        Category::factory()->count(3)->create();

        $response = $this->actingAs($this->admin)->get(route('admin.categories.export', 'xlsx'));
        $response->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        file_put_contents($path, $response->streamedContent());

        $this->assertStringStartsWith('PK', file_get_contents($path));
        $this->assertCount(3, SimpleExcelReader::create($path)->getRows()->all());
    }

    public function test_export_neutralises_formula_injection(): void
    {
        Post::factory()->create(['title' => ['en' => '=HYPERLINK("http://evil.test","click")']]);

        $rows = $this->csvRows($this->actingAs($this->admin)->get(route('admin.posts.export', 'csv'))->streamedContent());

        $this->assertStringStartsWith("'=", $rows[0]['title_en']);
        $this->assertSame("'@x", app(SpreadsheetService::class)->sanitizeCell('@x'));
        $this->assertSame(42, app(SpreadsheetService::class)->sanitizeCell(42));
    }

    public function test_exports_require_authorization_and_a_known_format(): void
    {
        $this->get(route('admin.posts.export', 'csv'))->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get(route('admin.categories.export', 'csv'))->assertForbidden();
        $this->actingAs($this->admin)->get('/admin/posts/export/pdf')->assertNotFound();

        config(['spreadsheets.export_formats' => ['csv']]);
        $this->actingAs($this->admin)->get(route('admin.posts.export', 'xlsx'))->assertNotFound();
    }

    public function test_category_export_can_be_imported_back(): void
    {
        Category::factory()->create(['name' => ['en' => 'Roundtrip', 'fr' => 'Aller-retour'], 'slug' => 'roundtrip']);

        $csv = $this->actingAs($this->admin)->get(route('admin.categories.export', 'csv'))->streamedContent();

        Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', $this->csvUpload($csv))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.updated', 1)
            ->assertSet('result.created', 0);

        $this->assertSame('Aller-retour', Category::firstWhere('slug', 'roundtrip')->getTranslation('name', 'fr'));
    }

    public function test_import_creates_updates_and_reports_invalid_rows(): void
    {
        Category::factory()->create(['name' => ['en' => 'Old name'], 'slug' => 'existing', 'is_active' => true]);

        $csv = "Slug,Name EN,Name FR,Is Active\n"
            ."existing,New name,,0\n"
            .",Brand new,Tout neuf,1\n"
            ."Bad Slug!,Has bad slug,,1\n"
            ."another,,,1\n"
            .",,,\n";

        $component = Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', $this->csvUpload($csv))
            ->call('import')
            ->assertHasNoErrors();

        $result = $component->get('result');
        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['updated']);
        $this->assertSame(2, $result['failed']);
        $this->assertSame([4, 5], array_column($result['errors'], 'row'));

        $existing = Category::firstWhere('slug', 'existing');
        $this->assertSame('New name', $existing->name);
        $this->assertFalse($existing->is_active);
        $this->assertSame('Tout neuf', Category::firstWhere('slug', 'brand-new')->getTranslation('name', 'fr'));
        $this->assertDatabaseMissing('categories', ['slug' => 'another']);

        $component->assertSee(__('imports.row'));
    }

    public function test_import_rejects_slugs_of_trashed_categories(): void
    {
        Category::factory()->create(['slug' => 'binned'])->delete();

        $result = Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', $this->csvUpload("slug,name_en\nbinned,Revived\n"))
            ->call('import')
            ->get('result');

        $this->assertSame(1, $result['failed']);
        $this->assertStringContainsString('binned', $result['errors'][0]['messages'][0]);
        $this->assertTrue(Category::onlyTrashed()->where('slug', 'binned')->exists());
    }

    public function test_import_reports_missing_required_columns(): void
    {
        $result = Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', $this->csvUpload("slug,title\nx,y\n"))
            ->call('import')
            ->get('result');

        $this->assertSame(0, $result['created']);
        $this->assertStringContainsString('name_en', $result['errors'][0]['messages'][0]);
    }

    public function test_import_stops_at_the_row_limit(): void
    {
        config(['spreadsheets.max_import_rows' => 2]);

        $result = Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', $this->csvUpload("name_en\nOne\nTwo\nThree\nFour\n"))
            ->call('import')
            ->get('result');

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(2, Category::count());
    }

    public function test_import_accepts_xlsx(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        SimpleExcelWriter::create($path)->addRow(['name_en' => 'From Excel', 'is_active' => 1])->close();

        Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', UploadedFile::fake()->createWithContent('cats.xlsx', file_get_contents($path)))
            ->call('import')
            ->assertSet('result.created', 1);

        $this->assertDatabaseHas('categories', ['slug' => 'from-excel']);
    }

    public function test_upload_validation_rejects_bad_files(): void
    {
        $page = Livewire::actingAs($this->admin)->test('pages::admin.categories.import');

        $page->set('file', UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;'))
            ->call('import')
            ->assertHasErrors('file');

        $page->set('file', UploadedFile::fake()->image('photo.jpg'))
            ->call('import')
            ->assertHasErrors('file');

        config(['spreadsheets.max_import_kb' => 1]);
        $page->set('file', $this->csvUpload("name_en\n".str_repeat("Row\n", 1000)))
            ->call('import')
            ->assertHasErrors('file');

        $this->assertSame(0, Category::count());
    }

    public function test_import_requires_permission(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.categories.import'))->assertForbidden();

        Livewire::actingAs(User::factory()->create())
            ->test('pages::admin.categories.import')
            ->assertForbidden();
    }
}

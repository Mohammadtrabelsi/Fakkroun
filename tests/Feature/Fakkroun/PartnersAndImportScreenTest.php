<?php

namespace Tests\Feature\Fakkroun;

use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionImportBatch;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PartnersAndImportScreenTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_admins_manage_partners_shown_on_the_home_page(): void
    {
        Storage::fake('public');

        Livewire::actingAs($this->admin)->test('pages::admin.partners')
            ->set('name', 'Blue Coast NGO')
            ->set('url', 'https://example.org')
            ->set('type', 'sponsor')
            ->set('logo', UploadedFile::fake()->image('logo.png', 120, 60))
            ->call('save')
            ->assertHasNoErrors();

        $partner = Partner::query()->firstOrFail();
        $this->assertSame('sponsor', $partner->type);
        Storage::disk('public')->assertExists($partner->logo_path);
        $this->get('/')->assertSee('Blue Coast NGO', false);

        Livewire::actingAs($this->admin)->test('pages::admin.partners')->call('edit', $partner->id)->set('isActive', false)->call('save');
        $this->get('/')->assertDontSee('alt="Blue Coast NGO"', false);

        Livewire::actingAs($this->admin)->test('pages::admin.partners')->call('delete', $partner->id);
        $this->assertSame(0, Partner::count());
        Storage::disk('public')->assertMissing($partner->logo_path);
    }

    public function test_partners_page_is_admin_only(): void
    {
        $moderator = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Moderator->value));

        $this->actingAs($moderator)->get(route('admin.partners'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.partners'))->assertOk();
    }

    public function test_import_screen_maps_previews_and_imports(): void
    {
        Storage::fake('local');
        $category = Category::factory()->create(['slug' => 'oceans']);
        $csv = "Category,Difficulty,Question,Choice 1,Choice 2,Choice 3,Choice 4,Correct\n"
            ."oceans,easy,Which ocean is the largest on Earth?,Atlantic,Indian,Pacific,Arctic,C\n";

        $page = Livewire::actingAs($this->admin)->test('pages::admin.questions.import')
            ->set('file', UploadedFile::fake()->createWithContent('questions.csv', $csv))
            ->assertSet('mapping.category', 'Category')
            ->assertSet('mapping.choice_1', 'Choice 1')
            ->call('runPreview');

        $this->assertSame([], $page->get('preview')[0]['errors']);
        $this->assertSame(0, Question::count());

        $page->call('start')->assertHasNoErrors();

        $this->assertSame(1, QuestionImportBatch::query()->where('status', 'completed')->count());
        $this->assertSame(1, Question::query()->where('category_id', $category->id)->count());
        $this->actingAs($this->admin)->get(route('admin.questions.import'))->assertOk()->assertSee('questions.csv');
    }
}

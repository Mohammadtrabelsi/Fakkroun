<?php

namespace Tests\Feature\Fakkroun;

use App\Enums\UserRole;
use App\Enums\ValidationPolicy;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use Database\Seeders\FakkrounCategorySeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Spatie\SimpleExcel\SimpleExcelReader;
use Tests\TestCase;

class CategoryHierarchyTest extends TestCase
{
    use RefreshDatabase;

    private CategoryService $service;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->service = app(CategoryService::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_a_subcategory_belongs_to_its_parent(): void
    {
        $ocean = Category::factory()->create();
        $coral = $this->service->create(['name' => ['en' => 'Coral reefs'], 'parent_id' => $ocean->id]);

        $this->assertTrue($coral->isSubcategory());
        $this->assertTrue($ocean->children->contains($coral));
        $this->assertTrue($coral->parent->is($ocean));
    }

    public function test_subcategories_cannot_be_nested_further(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        $this->expectException(DomainActionException::class);

        $this->service->create(['name' => ['en' => 'Too deep'], 'parent_id' => $child->id]);
    }

    public function test_a_category_cannot_be_its_own_parent(): void
    {
        $category = Category::factory()->create();

        $this->expectException(DomainActionException::class);

        $this->service->update($category, ['parent_id' => $category->id]);
    }

    public function test_a_category_with_children_cannot_become_a_subcategory(): void
    {
        $parent = Category::factory()->create();
        Category::factory()->create(['parent_id' => $parent->id]);
        $other = Category::factory()->create();

        $this->expectException(DomainActionException::class);

        $this->service->update($parent, ['parent_id' => $other->id]);
    }

    public function test_tree_lists_roots_in_order_with_their_children(): void
    {
        $second = Category::factory()->create(['sort_order' => 2]);
        $first = Category::factory()->create(['sort_order' => 1]);
        $child = Category::factory()->create(['parent_id' => $first->id]);
        Category::factory()->inactive()->create(['sort_order' => 3]);

        $tree = $this->service->tree(activeOnly: true);

        $this->assertSame([$first->id, $second->id], $tree->pluck('id')->all());
        $this->assertSame([$child->id], $tree->first()->children->pluck('id')->all());
    }

    public function test_reorder_rewrites_sort_order(): void
    {
        [$a, $b, $c] = Category::factory()->count(3)->create();

        $this->service->reorder([$c->id, $a->id, $b->id]);

        $this->assertSame([$c->id, $a->id, $b->id], Category::query()->ordered()->pluck('id')->all());
    }

    public function test_admin_form_saves_hierarchy_and_question_rules_in_every_language(): void
    {
        $parent = Category::factory()->create();

        Livewire::actingAs($this->admin)
            ->test('admin.categories.form')
            ->set('name.en', 'Mangroves')
            ->set('name.fr', 'Mangroves FR')
            ->set('name.ar', 'أشجار المانغروف')
            ->set('parentId', (string) $parent->id)
            ->set('sortOrder', 4)
            ->set('icon', 'tree')
            ->set('color', '#2f855a')
            ->set('validationPolicy', 'moderator')
            ->set('requiresReference', true)
            ->set('allowCommunitySubmissions', false)
            ->call('save')
            ->assertHasNoErrors();

        $category = Category::query()->where('parent_id', $parent->id)->firstOrFail();
        $this->assertSame('Mangroves FR', $category->getTranslation('name', 'fr'));
        $this->assertSame(ValidationPolicy::Moderator, $category->validation_policy);
        $this->assertTrue($category->requires_reference);
        $this->assertFalse($category->allow_community_submissions);
        $this->assertSame('#2f855a', $category->color);
    }

    public function test_admin_form_reports_an_invalid_parent(): void
    {
        $root = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $root->id]);

        Livewire::actingAs($this->admin)
            ->test('admin.categories.form')
            ->set('name.en', 'Nested')
            ->set('parentId', (string) $child->id)
            ->call('save')
            ->assertHasErrors('parentId');
    }

    public function test_admin_form_rejects_a_bad_colour(): void
    {
        Livewire::actingAs($this->admin)
            ->test('admin.categories.form')
            ->set('name.en', 'Colourful')
            ->set('color', 'green')
            ->call('save')
            ->assertHasErrors('color');
    }

    public function test_export_and_import_keep_the_parent(): void
    {
        $parent = Category::factory()->create(['slug' => 'oceans']);
        Category::factory()->create(['slug' => 'coral', 'parent_id' => $parent->id, 'validation_policy' => 'community']);

        $csv = $this->downloadedContent(Livewire::actingAs($this->admin)->test('pages::admin.categories.index')->call('export', 'csv'));

        $path = tempnam(sys_get_temp_dir(), 'csv').'.csv';
        file_put_contents($path, $csv);
        $row = collect(SimpleExcelReader::create($path)->getRows()->all())->firstWhere('slug', 'coral');
        $this->assertSame('oceans', $row['parent_slug']);
        $this->assertSame('community', $row['validation_policy']);

        Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', UploadedFile::fake()->createWithContent('categories.csv', "slug,name_en,parent_slug\nkelp,Kelp forests,oceans\n"))
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('result.created', 1);

        $this->assertSame($parent->id, Category::firstWhere('slug', 'kelp')->parent_id);
    }

    public function test_import_reports_an_unknown_parent(): void
    {
        Livewire::actingAs($this->admin)->test('pages::admin.categories.import')
            ->set('file', UploadedFile::fake()->createWithContent('categories.csv', "slug,name_en,parent_slug\nkelp,Kelp forests,missing\n"))
            ->call('import')
            ->assertSet('result.created', 0);

        $this->assertNull(Category::firstWhere('slug', 'kelp'));
    }

    public function test_initial_categories_are_seeded_once_in_three_languages(): void
    {
        $this->seed(FakkrounCategorySeeder::class);
        $this->seed(FakkrounCategorySeeder::class);

        $this->assertSame(13, Category::count());

        $green = Category::firstWhere('slug', 'green-economy');
        $this->assertSame('الاقتصاد الأخضر', $green->getTranslation('name', 'ar'));
        $this->assertSame('Économie verte', $green->getTranslation('name', 'fr'));
        $this->assertSame(ValidationPolicy::Admin, $green->validation_policy);
        $this->assertTrue($green->requires_reference);
        $this->assertSame(1, $green->sort_order);

        $this->assertSame(ValidationPolicy::Moderator, Category::firstWhere('slug', 'general-knowledge')->validation_policy);
    }

    public function test_seeder_does_not_overwrite_admin_changes(): void
    {
        $this->seed(FakkrounCategorySeeder::class);
        Category::firstWhere('slug', 'sports')->update(['validation_policy' => 'community']);

        $this->seed(FakkrounCategorySeeder::class);

        $this->assertSame(ValidationPolicy::Community, Category::firstWhere('slug', 'sports')->validation_policy);
    }

    public function test_moderator_scope_includes_subcategories_of_assigned_categories(): void
    {
        $moderator = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Moderator->value));
        $assigned = Category::factory()->create();
        $child = Category::factory()->create(['parent_id' => $assigned->id]);
        $other = Category::factory()->create();

        $this->actingAs($this->admin);
        $this->service->syncModeratedCategories($moderator, [$assigned->id]);

        $this->assertTrue($moderator->can('moderate', $assigned));
        $this->assertTrue($moderator->can('moderate', $child));
        $this->assertFalse($moderator->can('moderate', $other));
    }

    public function test_admins_moderate_every_category_and_players_none(): void
    {
        $category = Category::factory()->create();
        $player = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Player->value));

        $this->assertTrue($this->admin->can('moderate', $category));
        $this->assertFalse($player->can('moderate', $category));
    }

    public function test_only_moderators_can_be_assigned_categories(): void
    {
        $player = User::factory()->create();

        $this->expectException(DomainActionException::class);

        $this->service->syncModeratedCategories($player, [Category::factory()->create()->id]);
    }

    public function test_moderator_assignments_are_saved_from_the_admin_page(): void
    {
        $moderator = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Moderator->value));
        $category = Category::factory()->create();

        $this->actingAs($this->admin)->get(route('admin.moderators'))->assertOk()->assertSee($moderator->email);

        Livewire::actingAs($this->admin)
            ->test('pages::admin.moderators')
            ->set("assignments.{$moderator->id}", [(string) $category->id])
            ->call('save', $moderator->id);

        $this->assertSame([$category->id], $moderator->moderatedCategories()->pluck('categories.id')->all());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'categories',
            'description' => 'moderator categories updated',
            'subject_id' => $moderator->id,
        ]);
    }
}

<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\Models\Room;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class RoomCategoryDefaultsTest extends GameplayTestCase
{
    public function test_lobby_preselects_categories_marked_as_default_in_the_back_office(): void
    {
        $default = Category::factory()->create(['is_default_selected' => true]);
        Category::factory()->create(['is_default_selected' => true, 'is_active' => false]);
        Category::factory()->create(['is_default_selected' => true, 'parent_id' => $default->id]);

        Livewire::actingAs($this->player())->test('pages::rooms.create')
            ->assertSet('categoryIds', [$default->id])
            ->call('create')
            ->assertHasNoErrors();

        $this->assertSame([$default->id], Room::query()->latest('id')->firstOrFail()->setting('category_ids'));
    }

    public function test_minimum_number_of_categories_comes_from_platform_settings(): void
    {
        $second = Category::factory()->create();
        app(PlatformSettings::class)->update(['game' => ['min_categories' => 2]]);

        Livewire::actingAs($this->player())->test('pages::rooms.create')
            ->call('toggleCategory', $this->category->id)
            ->call('create')
            ->assertHasErrors('category_ids')
            ->assertSee(__('play.errors.min_categories', ['min' => 2]))
            ->call('toggleCategory', $second->id)
            ->call('create')
            ->assertHasNoErrors();
    }

    public function test_minimum_is_capped_at_the_number_of_active_categories(): void
    {
        app(PlatformSettings::class)->update(['game' => ['min_categories' => 5]]);

        $room = $this->room($this->player()); // only one category exists
        $this->assertSame([$this->category->id], $room->setting('category_ids'));

        Category::factory()->create();
        $this->expectException(ValidationException::class);
        $this->room($this->player());
    }

    public function test_admin_marks_a_category_as_preselected(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));

        Livewire::actingAs($admin)->test('admin.categories.form', ['category' => $this->category])
            ->set('isDefaultSelected', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue($this->category->fresh()->is_default_selected);
    }
}

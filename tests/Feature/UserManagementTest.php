<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_create_user_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/users/create')->assertOk();
    }

    public function test_non_admin_cannot_view_create_user_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/users/create')->assertForbidden();
    }

    public function test_admin_can_create_a_user_with_roles(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('admin.users.form')
            ->set('name', 'New Person')
            ->set('email', 'new-person@example.com')
            ->set('password', 'password123')
            ->set('roles', ['admin'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $created = User::where('email', 'new-person@example.com')->first();

        $this->assertNotNull($created);
        $this->assertTrue($created->hasRole('admin'));
    }

    public function test_admin_can_update_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create(['name' => 'Before']);

        Livewire::actingAs($admin)
            ->test('admin.users.form', ['user' => $user])
            ->set('name', 'After')
            ->set('roles', ['admin'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.users.index'));

        $user->refresh();

        $this->assertSame('After', $user->name);
        $this->assertTrue($user->hasRole('admin'));
    }

    public function test_admin_can_remove_a_role_from_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('admin.users.form', ['user' => $user])
            ->set('roles', [])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_admin_can_delete_a_user(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.users.index')
            ->call('deleteUser', $user->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted($user);
    }

    public function test_user_list_can_be_filtered_by_role(): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Filter Admin']);
        User::factory()->create(['name' => 'Filter Member']);

        Livewire::actingAs($admin)
            ->test('pages::admin.users.index')
            ->set('role', 'member')
            ->assertSee('Filter Member')
            ->assertDontSee('Filter Admin')
            ->set('role', 'admin')
            ->assertSee('Filter Admin')
            ->assertDontSee('Filter Member');
    }

    public function test_delete_user_button_asks_for_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->create(['name' => 'Confirm User']);

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertSee('wire:confirm="Move Confirm User to the trash? They will not be able to sign in until restored."', false);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.users.index')
            ->call('deleteUser', $admin->id)
            ->assertHasErrors('toggle');

        $this->assertNotNull($admin->fresh());
    }

    public function test_non_admin_cannot_delete_a_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::admin.users.index')
            // The screen itself refuses to mount, so no action can be called on it.
            ->assertForbidden();

        $this->assertNotNull($other->fresh());
    }

    public function test_users_link_points_to_users_route_and_is_active_there(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->get('/admin/users')
            ->assertOk()
            ->assertSee('href="'.route('admin.users.index').'" wire:navigate class="active"', false);
    }
}

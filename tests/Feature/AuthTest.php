<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_login_page_renders(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_register_page_renders(): void
    {
        $this->get('/register')->assertOk();
    }

    public function test_forgot_password_page_renders(): void
    {
        $this->get('/forgot-password')->assertOk();
    }

    public function test_user_can_log_in_with_correct_credentials(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_log_in_with_wrong_password(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Livewire::test('pages::auth.login')
            ->set('email', $user->email)
            ->set('password', 'wrong-password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_staff_login_page_renders_separately(): void
    {
        $this->get('/admin/login')->assertOk()->assertSee(__('login.staff_title'));
    }

    public function test_admin_signs_in_through_the_staff_portal(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        Livewire::test('pages::auth.admin-login')
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_reviewer_lands_on_the_moderation_queue(): void
    {
        $moderator = User::factory()->create(['password' => bcrypt('password')]);
        $moderator->assignRole('moderator');

        Livewire::test('pages::auth.admin-login')
            ->set('email', $moderator->email)
            ->set('password', 'password')
            ->call('login')
            ->assertRedirect(route('admin.moderation'));
    }

    public function test_staff_cannot_sign_in_through_the_player_login(): void
    {
        $admin = User::factory()->admin()->create(['password' => bcrypt('password')]);

        Livewire::test('pages::auth.login')
            ->set('email', $admin->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_players_cannot_sign_in_through_the_staff_portal(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password')]);

        Livewire::test('pages::auth.admin-login')
            ->set('email', $user->email)
            ->set('password', 'password')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_guests_are_sent_to_the_matching_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/admin/login');
        $this->get('/play')->assertRedirect('/login');
    }

    public function test_user_can_register(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'jane@example.com']);
        $this->assertAuthenticated();
    }

    public function test_registration_requires_matching_password_confirmation(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'nope')
            ->call('register')
            ->assertHasErrors('password');
    }

    public function test_guest_is_redirected_away_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_non_admin_cannot_access_admin_dashboard(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/dashboard')->assertForbidden();
    }

    public function test_admin_can_access_admin_dashboard(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/dashboard')->assertOk();
    }

    public function test_admin_can_toggle_another_users_admin_status(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.users.index')
            ->call('toggleAdmin', $user->id)
            ->assertHasNoErrors();

        $this->assertTrue($user->fresh()->hasRole('admin'));
    }

    public function test_admin_cannot_revoke_their_own_admin_status(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.users.index')
            ->call('toggleAdmin', $admin->id)
            ->assertHasErrors('toggle');

        $this->assertTrue($admin->fresh()->hasRole('admin'));
    }

    public function test_non_admin_cannot_toggle_admin_status(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::admin.users.index')
            ->call('toggleAdmin', $other->id)
            ->assertForbidden();

        $this->assertFalse($other->fresh()->hasRole('admin'));
    }

    public function test_staff_return_to_the_staff_portal_after_logging_out(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post('/logout')->assertRedirect(route('admin.login'));
    }

    public function test_user_can_log_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/logout')
            ->assertRedirect('/');

        $this->assertGuest();
    }
}

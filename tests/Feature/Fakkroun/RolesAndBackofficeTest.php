<?php

namespace Tests\Feature\Fakkroun;

use App\Enums\UserRole;
use App\Http\Middleware\EnsureBackofficeAccess;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class RolesAndBackofficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function userWithRole(UserRole $role): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role->value));
    }

    public function test_seeder_creates_every_platform_role(): void
    {
        $this->assertEqualsCanonicalizing(
            UserRole::values(),
            Role::query()->pluck('name')->all(),
        );
    }

    public function test_admin_role_holds_every_permission(): void
    {
        $admin = Role::findByName('admin', 'web');

        $this->assertSame(Permission::count(), $admin->permissions()->count());
    }

    public function test_moderator_can_enter_the_backoffice_but_not_manage_users(): void
    {
        $moderator = $this->userWithRole(UserRole::Moderator);

        $this->assertTrue($moderator->canAccessBackoffice());
        $this->assertTrue($moderator->can('moderate questions'));
        $this->assertFalse($moderator->can('manage users'));
        $this->assertFalse($moderator->can('manage questions'));
    }

    public function test_player_and_advertiser_cannot_enter_the_backoffice(): void
    {
        $this->assertFalse($this->userWithRole(UserRole::Player)->canAccessBackoffice());
        $this->assertFalse($this->userWithRole(UserRole::Advertiser)->canAccessBackoffice());
        $this->assertTrue($this->userWithRole(UserRole::Player)->can('play games'));
    }

    public function test_seeder_is_idempotent(): void
    {
        $permissions = Permission::count();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame($permissions, Permission::count());
        $this->assertSame(count(UserRole::cases()), Role::count());
    }

    public function test_web_registration_assigns_the_player_role(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('email', 'jane@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertTrue(User::firstWhere('email', 'jane@example.com')->hasRole('player'));
    }

    public function test_api_registration_assigns_the_player_role(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertTrue(User::firstWhere('email', 'jane@example.com')->hasRole('player'));
    }

    public function test_moderator_reaches_backoffice_screens_without_a_permission_of_their_own(): void
    {
        $moderator = $this->userWithRole(UserRole::Moderator);

        $this->actingAs($moderator)->get(route('admin.notifications.index'))->assertOk();
    }

    public function test_moderator_is_still_blocked_from_admin_only_screens(): void
    {
        $moderator = $this->userWithRole(UserRole::Moderator);

        $this->actingAs($moderator)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.game-settings'))->assertForbidden();
        $this->actingAs($moderator)->get(route('admin.moderators'))->assertForbidden();
    }

    public function test_player_cannot_reach_any_backoffice_screen(): void
    {
        $player = $this->userWithRole(UserRole::Player);

        $this->actingAs($player)->get(route('admin.notifications.index'))->assertForbidden();
    }

    public function test_backoffice_middleware_rejects_guests_with_403(): void
    {
        $request = Request::create('/admin');
        $request->setUserResolver(fn () => null);

        try {
            (new EnsureBackofficeAccess)->handle($request, fn () => new Response('passed'));
            $this->fail('Expected a 403 HttpException.');
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
    }

    public function test_sidebar_shows_admin_links_by_permission(): void
    {
        $admin = User::factory()->admin()->create();
        $moderator = $this->userWithRole(UserRole::Moderator);

        $this->actingAs($admin)->get(route('dashboard'))
            ->assertSee(route('admin.game-settings'), false)
            ->assertSee(route('admin.moderators'), false);

        $this->actingAs($moderator)->get(route('dashboard'))
            ->assertDontSee(route('admin.game-settings'), false)
            ->assertDontSee(route('admin.users.index'), false);
    }
}

<?php

namespace Tests\Feature\Fakkroun;

use App\Enums\AccountStatus;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\UserService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class AccountSuspensionTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    private function member(): User
    {
        return User::factory()->create([
            'email' => 'member@example.com',
            'password' => Hash::make('password123'),
        ]);
    }

    public function test_new_accounts_are_active(): void
    {
        $user = User::factory()->create()->fresh();

        $this->assertSame(AccountStatus::Active, $user->status);
        $this->assertFalse($user->isSuspended());
    }

    public function test_admin_can_suspend_a_user_from_the_admin_list(): void
    {
        $user = $this->member();

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('suspendUser', $user->id)
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertTrue($user->isSuspended());
        $this->assertNotNull($user->suspended_at);
        $this->assertSame($this->admin->id, $user->suspended_by);
    }

    public function test_admin_cannot_suspend_themselves(): void
    {
        $this->expectException(DomainActionException::class);

        app(UserService::class)->suspend($this->admin, $this->admin);
    }

    public function test_suspension_revokes_api_tokens(): void
    {
        $user = $this->member();
        $user->createToken('phone');

        app(UserService::class)->suspend($this->admin, $user, 'Spam');

        $this->assertSame(0, $user->tokens()->count());
        $this->assertSame('Spam', $user->fresh()->suspension_reason);
    }

    public function test_suspended_user_cannot_sign_in_on_the_web(): void
    {
        $user = $this->member();
        app(UserService::class)->suspend($this->admin, $user);

        Livewire::test('pages::auth.login')
            ->set('email', 'member@example.com')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasErrors('email');

        $this->assertGuest();
    }

    public function test_suspended_user_cannot_get_an_api_token(): void
    {
        $user = $this->member();
        app(UserService::class)->suspend($this->admin, $user);

        $this->postJson('/api/auth/token', [
            'email' => 'member@example.com',
            'password' => 'password123',
            'device_name' => 'phone',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_wrong_password_does_not_reveal_that_an_account_is_suspended(): void
    {
        $user = $this->member();
        app(UserService::class)->suspend($this->admin, $user);

        $response = $this->postJson('/api/auth/login', [
            'email' => 'member@example.com',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $this->assertNotSame(__('account.suspended'), $response->json('errors.email.0'));
    }

    public function test_signed_in_user_is_signed_out_once_suspended(): void
    {
        $user = $this->member();

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        app(UserService::class)->suspend($this->admin, $user);

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_reactivated_user_can_sign_in_again(): void
    {
        $user = $this->member();
        $service = app(UserService::class);
        $service->suspend($this->admin, $user, 'Mistake');

        Livewire::actingAs($this->admin)
            ->test('pages::admin.users.index')
            ->call('reactivateUser', $user->id);

        $user->refresh();
        $this->assertFalse($user->isSuspended());
        $this->assertNull($user->suspension_reason);
        $this->assertNotNull($service->authenticate('member@example.com', 'password123'));
    }

    public function test_users_list_can_be_filtered_by_status(): void
    {
        $suspended = $this->member();
        app(UserService::class)->suspend($this->admin, $suspended);
        User::factory()->create(['email' => 'active@example.com']);

        $emails = app(UserService::class)->searchPaginated(null, 50, ['status' => 'suspended'])
            ->getCollection()->pluck('email')->all();

        $this->assertSame(['member@example.com'], $emails);
    }

    public function test_api_suspend_and_reactivate_require_manage_users(): void
    {
        $user = $this->member();
        $other = User::factory()->create();

        $this->actingAs($other)->postJson("/api/users/{$user->id}/suspend")->assertForbidden();

        $this->actingAs($this->admin)
            ->postJson("/api/users/{$user->id}/suspend", ['reason' => 'Abuse'])
            ->assertOk()
            ->assertJsonPath('status', 'suspended');

        $this->actingAs($this->admin)
            ->postJson("/api/users/{$user->id}/reactivate")
            ->assertOk()
            ->assertJsonPath('status', 'active');
    }

    public function test_last_seen_is_recorded_for_signed_in_users(): void
    {
        $user = $this->member();
        $this->assertNull($user->last_seen_at);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertNotNull($user->fresh()->last_seen_at);
    }
}

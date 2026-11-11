<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SanctumTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_token_issuance_and_bearer_access(): void
    {
        User::factory()->create(['email' => 'token@example.com', 'password' => 'secret-pass']);

        $token = $this->postJson('/api/auth/token', [
            'email' => 'token@example.com',
            'password' => 'secret-pass',
            'device_name' => 'test-suite',
        ])->assertCreated()->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/profile')
            ->assertOk()
            ->assertJsonPath('email', 'token@example.com');
    }

    public function test_revoking_a_token_ends_its_access(): void
    {
        $user = User::factory()->create(['email' => 'revoke@example.com', 'password' => 'secret-pass']);

        $token = $this->postJson('/api/auth/token', [
            'email' => 'revoke@example.com',
            'password' => 'secret-pass',
            'device_name' => 'laptop',
        ])->json('token');

        $this->withHeader('Authorization', 'Bearer '.$token)->deleteJson('/api/auth/token')->assertNoContent();

        $this->assertSame(0, $user->tokens()->count());
        // Guards remember the resolved user for the whole test process; a real request starts fresh.
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/profile')->assertUnauthorized();
    }

    public function test_wrong_password_does_not_issue_a_token(): void
    {
        User::factory()->create(['email' => 'nope@example.com', 'password' => 'secret-pass']);

        $this->postJson('/api/auth/token', [
            'email' => 'nope@example.com',
            'password' => 'wrong-pass',
            'device_name' => 'laptop',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }
}

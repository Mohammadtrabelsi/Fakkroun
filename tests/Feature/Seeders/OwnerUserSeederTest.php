<?php

namespace Tests\Feature\Seeders;

use App\Models\User;
use Database\Seeders\OwnerUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerUserSeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        putenv('OWNER_PASSWORD');
        unset($_ENV['OWNER_PASSWORD'], $_SERVER['OWNER_PASSWORD']);

        parent::tearDown();
    }

    public function test_it_creates_a_verified_account(): void
    {
        $this->seed(OwnerUserSeeder::class);

        $user = User::where('email', OwnerUserSeeder::EMAIL)->firstOrFail();

        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertSame(OwnerUserSeeder::NAME, $user->name);
        $this->assertFalse($user->hasRole('admin'));
    }

    public function test_it_uses_owner_password_when_set(): void
    {
        putenv('OWNER_PASSWORD=secret-owner-pass');
        $_ENV['OWNER_PASSWORD'] = $_SERVER['OWNER_PASSWORD'] = 'secret-owner-pass';

        $this->seed(OwnerUserSeeder::class);

        $user = User::where('email', OwnerUserSeeder::EMAIL)->firstOrFail();
        $this->assertTrue(Hash::check('secret-owner-pass', $user->password));
    }

    public function test_rerunning_keeps_the_existing_password_and_verifies(): void
    {
        $existing = User::factory()->unverified()->create([
            'email' => OwnerUserSeeder::EMAIL,
            'password' => 'kept-password',
        ]);

        $this->seed(OwnerUserSeeder::class);

        $user = $existing->fresh();
        $this->assertSame(1, User::where('email', OwnerUserSeeder::EMAIL)->count());
        $this->assertTrue($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('kept-password', $user->password));
    }
}

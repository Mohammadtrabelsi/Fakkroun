<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Auth\LoginIdentifier;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

/** Players sign up and sign in with an email address or a phone number. */
class PhoneSignUpTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        config(['honeypot.enabled' => false]);
    }

    public function test_player_can_register_with_a_phone_number_and_play(): void
    {
        Livewire::test('pages::auth.register')
            ->set('name', 'Sami')
            ->set('login', '+216 22 123 456')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $user = User::where('phone', '+21622123456')->firstOrFail();

        $this->assertNull($user->email);
        $this->assertTrue($user->hasRole('player'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('rooms.create'))->assertOk();
    }

    public function test_phone_number_must_be_valid_and_unique(): void
    {
        User::factory()->create(['phone' => '+21622123456']);

        Livewire::test('pages::auth.register')
            ->set('name', 'Sami')
            ->set('login', '0021622123456')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors('login');

        Livewire::test('pages::auth.register')
            ->set('name', 'Sami')
            ->set('login', 'not a phone')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register')
            ->assertHasErrors('login');
    }

    public function test_player_can_log_in_with_a_phone_number(): void
    {
        $user = User::factory()->create([
            'email' => null,
            'phone' => '+21622123456',
            'password' => Hash::make('password123'),
        ]);
        $user->assignRole('player');

        Livewire::test('pages::auth.login')
            ->set('email', '+216 22-123-456')
            ->set('password', 'password123')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
    }

    public function test_api_registration_accepts_a_phone_number(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Sami',
            'phone' => '+21622123456',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertCreated();

        $this->assertDatabaseHas('users', ['phone' => '+21622123456', 'email' => null]);
    }

    public function test_api_registration_needs_an_email_or_a_phone(): void
    {
        $this->postJson('/api/auth/register', [
            'name' => 'Sami',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['email', 'phone']);
    }

    public function test_phone_numbers_are_normalized(): void
    {
        $this->assertSame('+21622123456', LoginIdentifier::normalizePhone(' 00216 (22) 123.456 '));
        $this->assertSame('phone', LoginIdentifier::column('+21622123456'));
        $this->assertSame('email', LoginIdentifier::column('a@b.tn'));
    }
}

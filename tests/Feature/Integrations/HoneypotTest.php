<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Spatie\Honeypot\Events\SpamDetectedEvent;
use Tests\TestCase;

class HoneypotTest extends TestCase
{
    use RefreshDatabase;

    private function fillRegistration($component)
    {
        return $component
            ->set('name', 'Real Person')
            ->set('login', 'person@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123');
    }

    public function test_public_forms_render_the_hidden_honeypot_fields(): void
    {
        foreach (['/register', '/forgot-password'] as $url) {
            $this->get($url)
                ->assertOk()
                ->assertSee('wire:model.defer="extraFields.my_name"', false)
                ->assertSee('wire:model.defer="extraFields.valid_from"', false)
                ->assertSee('style="display: none"', false);
        }
    }

    public function test_login_form_is_not_protected_so_password_managers_keep_working(): void
    {
        $this->get('/login')->assertOk()->assertDontSee('extraFields', false);
    }

    public function test_legitimate_registration_passes(): void
    {
        $this->fillRegistration(Livewire::test('pages::auth.register'))
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('users', ['email' => 'person@example.com']);
    }

    public function test_registration_with_a_filled_honeypot_is_rejected(): void
    {
        Event::fake([SpamDetectedEvent::class]);

        $this->fillRegistration(Livewire::test('pages::auth.register'))
            ->set('extraFields.my_name', 'i-am-a-bot')
            ->call('register')
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'person@example.com']);
        Event::assertDispatched(SpamDetectedEvent::class);
    }

    public function test_registration_submitted_too_fast_is_rejected(): void
    {
        config(['honeypot.amount_of_seconds' => 3]);

        $component = $this->fillRegistration(Livewire::test('pages::auth.register'));
        $component->call('register')->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'person@example.com']);
    }

    public function test_slow_human_submission_is_accepted_with_the_timing_check_on(): void
    {
        config(['honeypot.amount_of_seconds' => 3]);

        $component = $this->fillRegistration(Livewire::test('pages::auth.register'));

        $this->travel(5)->seconds();

        $component->call('register')->assertHasNoErrors()->assertRedirect(route('dashboard'));
        $this->assertDatabaseHas('users', ['email' => 'person@example.com']);
    }

    public function test_tampered_timestamp_is_rejected(): void
    {
        $this->fillRegistration(Livewire::test('pages::auth.register'))
            ->set('extraFields.valid_from', 'not-an-encrypted-timestamp')
            ->call('register')
            ->assertForbidden();
    }

    public function test_forgot_password_with_a_filled_honeypot_is_rejected(): void
    {
        User::factory()->create(['email' => 'known@example.com']);

        Livewire::test('pages::auth.forgot-password')
            ->set('email', 'known@example.com')
            ->set('extraFields.my_name', 'bot')
            ->call('sendResetLink')
            ->assertForbidden();

        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_protection_can_be_disabled(): void
    {
        config(['honeypot.enabled' => false]);

        $this->fillRegistration(Livewire::test('pages::auth.register'))
            ->set('extraFields.my_name', 'filled')
            ->call('register')
            ->assertHasNoErrors();
    }
}

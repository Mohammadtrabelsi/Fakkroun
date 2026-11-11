<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** House rule: every public form is rate-limited, per form and IP. */
class PublicFormThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_join_is_limited_per_ip(): void
    {
        config(['fakkroun.form_throttle.join-room' => [2, 60]]);

        foreach (range(1, 2) as $attempt) {
            Livewire::test('pages::rooms.join')
                ->set('nickname', 'Sami')
                ->set('code', 'NOPE99')
                ->call('join')
                ->assertHasErrors('code')
                ->assertDontSee(__('app.too_many_attempts', ['seconds' => 60]));
        }

        // The third attempt is refused before the code is even looked up.
        Livewire::test('pages::rooms.join')
            ->set('nickname', 'Sami')
            ->set('code', 'NOPE99')
            ->call('join')
            ->assertHasErrors('code')
            ->assertSee('Too many attempts');
    }

    public function test_password_reset_requests_are_limited(): void
    {
        config(['fakkroun.form_throttle.forgot-password' => [1, 60]]);

        Livewire::test('pages::auth.forgot-password')->set('email', 'someone@example.com')->call('sendResetLink');

        Livewire::test('pages::auth.forgot-password')
            ->set('email', 'someone@example.com')
            ->call('sendResetLink')
            ->assertHasErrors('email')
            ->assertSee('Too many attempts');
    }

    public function test_limits_are_separate_per_form(): void
    {
        config(['fakkroun.form_throttle.forgot-password' => [1, 60]]);

        Livewire::test('pages::auth.forgot-password')->set('email', 'someone@example.com')->call('sendResetLink');

        // Using up one form's allowance does not lock another form.
        Livewire::test('pages::rooms.join')
            ->set('nickname', 'Sami')
            ->set('code', 'NOPE99')
            ->call('join')
            ->assertDontSee('Too many attempts');
    }
}

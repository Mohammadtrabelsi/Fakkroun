<?php

namespace Tests\Feature\Fakkroun;

use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class LanguagePreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_interface_language_wins_over_the_session(): void
    {
        $user = User::factory()->create(['locale' => 'fr']);

        $this->actingAs($user)->withSession(['locale' => 'ar'])->get(route('dashboard'))->assertOk();

        $this->assertSame('fr', app()->getLocale());
    }

    public function test_session_language_applies_when_the_user_has_no_preference(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->withSession(['locale' => 'ar'])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }

    public function test_an_unsupported_saved_language_is_ignored(): void
    {
        $user = User::factory()->create(['locale' => 'xx']);

        $this->actingAs($user)->get(route('dashboard'))->assertOk();

        $this->assertSame(config('app.locale'), app()->getLocale());
        $this->assertNull($user->preferredLocale());
    }

    public function test_switching_language_while_signed_in_saves_the_preference(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('dashboard'))->get('/language/ar')->assertRedirect(route('dashboard'));

        $this->assertSame('ar', $user->fresh()->locale);
    }

    public function test_question_language_is_independent_and_falls_back(): void
    {
        $user = User::factory()->make(['locale' => 'fr', 'question_locale' => 'ar']);
        $this->assertSame('ar', $user->preferredQuestionLocale());

        $user->question_locale = null;
        $this->assertSame('fr', $user->preferredQuestionLocale());

        $user->locale = null;
        $this->assertSame(config('app.locale'), $user->preferredQuestionLocale());
    }

    public function test_preferences_can_be_saved_from_the_profile_page(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('locale', 'fr')
            ->set('question_locale', 'ar')
            ->call('updatePreferences')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertSame('fr', $user->locale);
        $this->assertSame('ar', $user->question_locale);
    }

    public function test_profile_rejects_an_unsupported_language(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('question_locale', 'xx')
            ->call('updatePreferences')
            ->assertHasErrors('question_locale');
    }

    public function test_service_rejects_an_unsupported_language(): void
    {
        $this->expectException(DomainActionException::class);

        app(UserService::class)->updatePreferences(User::factory()->create(), ['locale' => 'xx']);
    }

    public function test_empty_value_resets_a_preference(): void
    {
        $user = User::factory()->create(['question_locale' => 'ar']);

        app(UserService::class)->updatePreferences($user, ['question_locale' => '']);

        $this->assertNull($user->fresh()->question_locale);
    }

    public function test_preferences_can_be_updated_through_the_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->putJson('/api/profile/preferences', ['question_locale' => 'fr'])
            ->assertOk()
            ->assertJsonPath('question_locale', 'fr');

        $this->actingAs($user)
            ->putJson('/api/profile/preferences', ['locale' => 'de'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('locale');
    }

    public function test_registration_remembers_the_language_used_to_sign_up(): void
    {
        // SetLocale has already applied the visitor's language on a real request.
        app()->setLocale('fr');

        Livewire::test('pages::auth.register')
            ->set('name', 'Jane Doe')
            ->set('login', 'jane@example.com')
            ->set('password', 'password123')
            ->set('password_confirmation', 'password123')
            ->call('register');

        $this->assertSame('fr', User::firstWhere('email', 'jane@example.com')->locale);
    }

    public function test_avatar_can_be_uploaded_replaced_and_removed(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $component = Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('avatar', UploadedFile::fake()->image('me.png', 200, 200))
            ->assertHasNoErrors();

        $first = $user->fresh()->avatar_path;
        $this->assertNotNull($first);
        Storage::disk('public')->assertExists($first);

        $component->set('avatar', UploadedFile::fake()->image('me2.jpg', 200, 200))->assertHasNoErrors();

        $second = $user->fresh()->avatar_path;
        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);

        $component->call('removeAvatar');

        $this->assertNull($user->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($second);
    }

    public function test_avatar_must_be_a_small_image(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('avatar', UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'))
            ->assertHasErrors('avatar');

        Livewire::actingAs($user)
            ->test('pages::settings.profile')
            ->set('avatar', UploadedFile::fake()->image('big.png')->size(2048))
            ->assertHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_path);
    }
}

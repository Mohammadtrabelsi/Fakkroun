<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_can_be_switched(): void
    {
        $this->from('/login')
            ->get('/language/ar')
            ->assertRedirect('/login')
            ->assertSessionHas('locale', 'ar');
    }

    public function test_unsupported_locale_is_ignored(): void
    {
        $this->from('/login')
            ->get('/language/xx')
            ->assertRedirect('/login');

        $this->assertNull(session('locale'));
    }

    public function test_session_locale_is_applied_and_sets_rtl_direction(): void
    {
        $response = $this->withSession(['locale' => 'ar'])->get('/login');

        $response->assertOk();
        $response->assertSee('dir="rtl"', false);
        $this->assertSame('ar', app()->getLocale());
    }

    public function test_default_locale_uses_ltr_direction(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
    }

    public function test_third_available_locale_is_applied(): void
    {
        $response = $this->withSession(['locale' => 'fr'])->get('/login');

        $response->assertOk();
        $response->assertSee('dir="ltr"', false);
        $this->assertSame('fr', app()->getLocale());
    }

    public function test_unsupported_session_locale_falls_back_to_default(): void
    {
        // A stale/invalid locale in the session must be ignored by SetLocale,
        // leaving the application on its configured default.
        $response = $this->withSession(['locale' => 'zz'])->get('/login');

        $response->assertOk();
        $this->assertSame(config('app.locale'), app()->getLocale());
    }

    public function test_switching_locale_from_a_non_referer_page_redirects_back(): void
    {
        $this->from('/register')
            ->get('/language/fr')
            ->assertRedirect('/register')
            ->assertSessionHas('locale', 'fr');
    }
}

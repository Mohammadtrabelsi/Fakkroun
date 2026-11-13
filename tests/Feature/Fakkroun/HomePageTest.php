<?php

namespace Tests\Feature\Fakkroun;

use App\Models\Category;
use App\Models\Partner;
use Database\Seeders\FakkrounCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_renders_every_section_of_the_design(): void
    {
        $response = $this->get('/')->assertOk();

        foreach (['home.hero.create', 'home.turtle.title', 'home.how.title', 'home.categories.title', 'home.learn.title', 'home.who.title', 'home.community.title', 'home.partners.title', 'home.cta.title', 'home.footer.tagline'] as $key) {
            $response->assertSee(__($key), false);
        }

        $response->assertSee('id="categories"', false)
            ->assertSee('id="learn"', false)
            ->assertSee('id="partners"', false)
            ->assertSee('family=Baloo+Bhaijaan+2', false);
    }

    public function test_the_header_shows_sign_in_once_and_play_now_once_per_breakpoint(): void
    {
        $html = (string) $this->get('/')->assertOk()->getContent();
        $header = substr($html, (int) strpos($html, '<header'), (int) strpos($html, '</header>') - (int) strpos($html, '<header'));

        $this->assertSame(1, substr_count($header, '>'.__('home.nav.sign_in').'</a>'));
        // One next to the menu toggle (small screens), one in the bar (large screens).
        $this->assertSame(2, substr_count($header, '>'.__('home.nav.play_now').'</a>'));
    }

    public function test_the_mascot_has_two_eyes(): void
    {
        $html = $this->get('/')->getContent();

        // Both eye whites of the front-facing head (client requirement).
        $this->assertStringContainsString('cx="109" cy="54" r="5" fill="#fff"', $html);
        $this->assertStringContainsString('cx="123" cy="54" r="5" fill="#fff"', $html);
    }

    public function test_priority_categories_lead_and_the_rest_are_chips(): void
    {
        $this->seed(FakkrounCategorySeeder::class);

        $this->assertSame(7, Category::query()->where('is_priority', true)->count());

        $this->get('/')->assertOk()
            ->assertSee('Green Economy')
            ->assertSee('الاقتصاد الأخضر')
            ->assertSee('Circular production, green jobs, recycling and sustainable farming.')
            ->assertSee('Sports');
    }

    public function test_partners_come_from_the_database_with_placeholders_as_fallback(): void
    {
        $this->get('/')->assertSee(config('fakkroun.brand.partner_placeholder'));

        Partner::query()->create(['name' => 'Blue Coast Association', 'url' => 'https://example.org']);
        Partner::query()->create(['name' => 'Hidden partner', 'is_active' => false]);

        $this->get('/')
            ->assertSee('Blue Coast Association')
            ->assertDontSee('Hidden partner')
            ->assertDontSee(config('fakkroun.brand.partner_placeholder'));
    }

    public function test_join_form_sends_the_code_to_the_join_flow(): void
    {
        $this->get('/')->assertSee('action="'.route('rooms.join').'"', false)->assertSee('name="code"', false);
    }

    public function test_arabic_home_is_right_to_left_with_the_active_language_marked(): void
    {
        $this->withSession(['locale' => 'ar'])->get('/')
            ->assertOk()
            ->assertSee('dir="rtl"', false)
            ->assertSee(__('home.hero.title', [], 'ar'))
            ->assertSee('aria-current="true"', false);
    }
}

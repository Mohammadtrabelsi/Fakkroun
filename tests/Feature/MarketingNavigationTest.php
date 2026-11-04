<?php

namespace Tests\Feature;

use App\Models\Post;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingNavigationTest extends TestCase
{
    use RefreshDatabase;

    // The home page has its own Fakkroun layout (HomePageTest); the configurable
    // marketing navbar serves the other public pages, e.g. /docs.
    public function test_navbar_renders_items_from_config(): void
    {
        $this->get(route('docs'))
            ->assertOk()
            ->assertSee('href="'.url('/#features').'"', false)
            ->assertSee('href="'.route('docs').'"', false)
            ->assertSee('href="'.route('blog').'"', false);
    }

    public function test_navbar_items_can_be_changed_through_config(): void
    {
        config(['navigation.marketing' => [
            ['label' => 'Pricing', 'url' => '/pricing'],
        ]]);

        $this->get(route('docs'))
            ->assertOk()
            ->assertSee('href="'.url('/pricing').'"', false)
            ->assertSee('>Pricing<', false);
    }

    public function test_current_route_is_marked_in_the_navbar(): void
    {
        $html = $this->get('/blog')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('blog'), '/').'"[^>]*aria-current="page"/',
            $html,
        );
    }

    public function test_nested_route_marks_its_parent_item_as_current(): void
    {
        Post::factory()->published()->create(['title' => ['en' => 'Nested Story'], 'slug' => 'nested-story']);

        $html = $this->get('/blog/nested-story')->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('blog'), '/').'"[^>]*aria-current="page"/',
            $html,
        );
    }

    public function test_navbar_toggle_is_present_for_small_screens(): void
    {
        $this->get(route('docs'))
            ->assertOk()
            ->assertSee('data-nav-toggle', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('id="site-nav"', false);
    }
}

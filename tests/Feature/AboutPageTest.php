<?php

namespace Tests\Feature;

use Tests\TestCase;

class AboutPageTest extends TestCase
{
    public function test_about_page_renders_the_project_presentation(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee('Fakkroun')
            ->assertSee(__('about.features_title'))
            ->assertSee(__('about.start_title'))
            ->assertSee('Sadaka Jariya');
    }

    public function test_about_page_presents_the_story_rules_questions_partners_and_faq(): void
    {
        $this->get(route('about'))
            ->assertOk()
            ->assertSee(__('about.story_title'))
            ->assertSee(__('about.mission_title'))
            ->assertSee(__('about.audience_title'))
            ->assertSee(__('about.rules_title'))
            ->assertSee(__('about.quality_title'))
            ->assertSee(__('about.partners_title'))
            ->assertSee(__('about.faq_title'))
            ->assertSee(__('about.faq_6_q'))
            ->assertSee(__('about.start_5_cmd'))
            ->assertSee('href="'.route('terms').'"', false);
    }

    public function test_about_page_is_linked_from_the_navbar_and_footer(): void
    {
        $this->get(route('docs'))
            ->assertOk()
            ->assertSee('href="'.route('about').'"', false);
    }

    public function test_about_page_is_marked_as_current_in_the_navbar(): void
    {
        $html = $this->get(route('about'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression(
            '/<a\s[^>]*href="'.preg_quote(route('about'), '/').'"[^>]*aria-current="page"/',
            $html,
        );
    }
}

<?php

namespace Tests\Feature\Fakkroun;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TermsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_terms_show_the_advertiser_policy_chart_in_every_language(): void
    {
        foreach (array_keys(config('app.available_locales')) as $locale) {
            app()->setLocale($locale);

            $this->withSession(['locale' => $locale])->get(route('terms'))
                ->assertOk()
                ->assertSee(__('terms.title'))
                ->assertSee(__('terms.policy.caption'))
                ->assertSee(__('terms.policy.rows')[0]['principle'])
                ->assertSee(__('terms.packs.items')[0]['name'])
                ->assertSee(__('terms.account_types')[1]['name']);
        }
    }

    public function test_minimum_age_and_draft_notice_come_from_config(): void
    {
        config(['fakkroun.legal.minimum_age' => 8, 'fakkroun.legal.terms_draft' => true]);

        $this->get(route('terms'))
            ->assertSee(__('terms.sections.age.paragraphs.0', ['age' => 8]))
            ->assertSee(__('terms.draft_notice'));

        config(['fakkroun.legal.terms_draft' => false]);
        $this->get(route('terms'))->assertDontSee(__('terms.draft_notice'));
    }

    public function test_the_footer_links_to_the_terms(): void
    {
        $this->get(route('home'))->assertOk()->assertSee(route('terms'), false);
    }
}

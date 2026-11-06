<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\AdRelevance;
use App\Domain\Advertising\AdService;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\WalletService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\RoomService;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use App\Services\PlatformSettings;
use Tests\Feature\Gameplay\GameplayTestCase;

/** Story 16, slice 1: contextual relevance (S1), fatigue caps (S4), policy tick (S13). */
class SmartAdsTest extends GameplayTestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1, 'max_per_player_per_game' => 20, 'min_seconds_between' => 0]]);
    }

    /**
     * @param  array<string, mixed>  $campaign
     * @param  array<int, array<string, mixed>>  $creatives
     */
    private function campaign(string $name, array $campaign = [], array $creatives = [['headline' => 'Hello']]): Campaign
    {
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => $name]);
        app(WalletService::class)->purchase($advertiser, AdvertisingPackage::query()->create(['name' => 'Essential', 'impression_credits' => 100, 'sponsored_question_credits' => 0, 'price_minor' => 1000, 'currency' => 'TND']), $this->admin, 'INV-'.$name);

        $service = app(CampaignService::class);
        $model = $service->create($advertiser, $owner, ['name' => $name] + $campaign + ['max_per_player_per_game' => 20]);

        foreach ($creatives as $creative) {
            $service->addCreative($model, $owner, $creative);
        }

        $service->submit($model, $owner);

        return $service->approve($model, $this->admin, true);
    }

    /**
     * @return array{0: \App\Domain\Gameplay\Models\Room, 1: \Illuminate\Support\Collection<int, GameQuestion>, 2: User}
     */
    private function game(int $questions = 3): array
    {
        $this->publishQuestions($questions);
        $host = $this->player('Host');
        $room = $this->room($host, ['question_count' => $questions]);
        app(RoomService::class)->join($room, $this->player('Guest'));
        $game = app(GameEngine::class)->start($room, $host);

        return [$room->fresh(), $game->gameQuestions()->orderBy('position')->get(), $host];
    }

    public function test_a_campaign_targeting_the_topic_beats_an_untargeted_one(): void
    {
        $this->campaign('Anyone');
        $targeted = $this->campaign('Ocean NGO', ['target_category_ids' => [$this->category->id]]);
        [$room, $gqs, $host] = $this->game();

        $this->assertSame($targeted->id, app(AdService::class)->offer($room, $gqs[0], $host)->campaign_id);
    }

    public function test_keywords_in_the_question_raise_a_creative(): void
    {
        $this->campaign('Generic', [], [['headline' => 'Generic']]);
        $match = $this->campaign('Words', [], [['headline' => 'Matched', 'keywords' => ['zzqword']]]);
        [$room, $gqs, $host] = $this->game(1);
        $gqs[0]->question->translation($gqs[0]->language)->forceFill(['body' => 'Which ZZQWORD is the largest?'])->save();

        $this->assertSame($match->id, app(AdService::class)->offer($room, $gqs[0]->fresh(), $host)->campaign_id);
    }

    public function test_untargeted_ads_can_be_switched_off(): void
    {
        $this->campaign('Anyone');
        app(PlatformSettings::class)->update(['advertising' => ['allow_untargeted' => false]]);
        [$room, $gqs, $host] = $this->game();

        $this->assertNull(app(AdService::class)->offer($room, $gqs[0], $host));
    }

    public function test_campaigns_targeting_another_topic_are_never_shown(): void
    {
        $other = Category::factory()->create();
        $this->campaign('Elsewhere', ['target_category_ids' => [$other->id]]);
        [$room, $gqs, $host] = $this->game();

        $this->assertNull(app(AdService::class)->offer($room, $gqs[0], $host));
    }

    public function test_the_same_creative_is_capped_per_day_and_creatives_rotate(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['max_same_creative_per_day' => 1]]);
        $campaign = $this->campaign('Rotate', [], [['headline' => 'First'], ['headline' => 'Second']]);
        [$room, $gqs, $host] = $this->game(3);
        $ads = app(AdService::class);

        $first = $ads->offer($room, $gqs[0], $host);
        $second = $ads->offer($room, $gqs[1], $host);
        $third = $ads->offer($room, $gqs[2], $host);

        $this->assertNotSame($first->creative_id, $second->creative_id, 'The other creative of the campaign is shown next.');
        $this->assertNull($third, 'Both creatives reached today\'s cap.');
        $this->assertSame($campaign->id, $second->campaign_id);

        $this->travel(1)->days();
        $this->publishQuestions(1);
        [$room2, $gqs2] = $this->game(1);
        $this->assertNotNull($ads->offer($room2, $gqs2[0], $host), 'The cap resets the next day.');
    }

    public function test_relevance_scoring_rules(): void
    {
        $relevance = app(AdRelevance::class);

        $this->assertSame(2, $relevance->keywordHits(['océan', 'recyclage', 'x'], 'Quel OCÉAN… et le recyclage ?'));
        $this->assertSame(1, $relevance->keywordHits(['بحر'], 'ما هو أكبر بَحْر في العالم؟'), 'Arabic diacritics are ignored.');
    }

    public function test_approval_requires_the_policy_tick(): void
    {
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => 'NGO']);
        $service = app(CampaignService::class);
        $campaign = $service->create($advertiser, $owner, ['name' => 'Check']);
        $service->addCreative($campaign, $owner, ['headline' => 'Hi']);
        $service->submit($campaign, $owner);

        try {
            $service->approve($campaign, $this->admin);
            $this->fail('Approval must require the policy tick.');
        } catch (DomainActionException) {
        }

        $service->approve($campaign->fresh(), $this->admin, true);
        $this->assertNotNull($campaign->fresh()->policy_checked_at);
        $this->assertSame($this->admin->id, $campaign->fresh()->policy_checked_by);
    }

    public function test_advertisers_add_keywords_from_the_dashboard(): void
    {
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => 'NGO']);
        $campaign = app(CampaignService::class)->create($advertiser, $owner, ['name' => 'Words']);

        \Livewire\Livewire::actingAs($owner)->test('pages::advertiser.dashboard')
            ->set('creativeFor', $campaign->id)
            ->set('headline', 'Save the sea')
            ->set('keywords', 'ocean, sea ,، بحر, ocean')
            ->call('addCreative')
            ->assertHasNoErrors();

        $this->assertSame(['ocean', 'sea', 'بحر'], $campaign->creatives()->first()->keywords);
    }

    public function test_every_ad_rule_is_a_setting(): void
    {
        // Weights: with topic targeting worth nothing, keywords decide.
        app(PlatformSettings::class)->update(['advertising' => ['weight_topic' => 0, 'weight_keyword' => 5, 'delivery_minutes' => 3]]);
        $this->campaign('Topic', ['target_category_ids' => [$this->category->id]]);
        $words = $this->campaign('Words', [], [['headline' => 'Matched', 'keywords' => ['zzqword']]]);
        [$room, $gqs, $host] = $this->game(1);
        $gqs[0]->question->translation($gqs[0]->language)->forceFill(['body' => 'A zzqword question'])->save();

        $delivery = app(AdService::class)->offer($room, $gqs[0]->fresh(), $host);
        $this->assertSame($words->id, $delivery->campaign_id);
        $this->assertTrue($delivery->expires_at->between(now()->addMinutes(3)->subSecond(), now()->addMinutes(3)->addSecond()), 'Token lifetime comes from settings.');

        // The answer-reveal placement can be switched off too.
        app(PlatformSettings::class)->update(['advertising' => ['reveal_enabled' => false]]);
        [$room2, $gqs2, $host2] = $this->game(1);
        $this->assertNull(app(AdService::class)->offer($room2, $gqs2[0], $host2));
    }

    public function test_the_keyword_limit_is_a_setting(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['max_keywords_per_creative' => 2]]);
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => 'NGO']);
        $campaign = app(CampaignService::class)->create($advertiser, $owner, ['name' => 'Limit']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        app(CampaignService::class)->addCreative($campaign, $owner, ['headline' => 'Hi', 'keywords' => ['a1', 'b2', 'c3']]);
    }

    public function test_the_ad_card_uses_the_view_settings(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['view_ratio_percent' => 80, 'view_seconds' => 3, 'every_n_questions' => 1]]);
        $this->campaign('Anyone');
        [$room, , $host] = $this->game(1);

        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk();
        $html = view('partials.ads.card', ['ad' => app(AdService::class)->offerLobby($room, $host)?->load('creative') ?? \App\Domain\Advertising\Models\AdDelivery::query()->with('creative')->firstOrFail(), 'dark' => true])->render();

        $this->assertStringContainsString('>= 0.8', $html);
        $this->assertStringContainsString('3000', $html);
    }
}

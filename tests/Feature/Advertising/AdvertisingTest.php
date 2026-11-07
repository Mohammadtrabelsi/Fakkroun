<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\AdService;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\InsufficientCredits;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Domain\Advertising\Models\SponsoredDelivery;
use App\Domain\Advertising\ReconciliationService;
use App\Domain\Advertising\SponsoredQuestionService;
use App\Domain\Advertising\WalletService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Livewire\Livewire;
use LogicException;
use Tests\Feature\Gameplay\GameplayTestCase;

class AdvertisingTest extends GameplayTestCase
{
    private User $admin;

    private User $owner;

    private Advertiser $advertiser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        $this->owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $this->advertiser = Advertiser::query()->create(['user_id' => $this->owner->id, 'name' => 'Green School']);
    }

    private function package(int $impressions = 10, int $sponsored = 2): AdvertisingPackage
    {
        return AdvertisingPackage::query()->create(['name' => 'Starter', 'impression_credits' => $impressions, 'sponsored_question_credits' => $sponsored, 'price_minor' => 5000, 'currency' => 'TND']);
    }

    private function activeCampaign(): Campaign
    {
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($this->advertiser, $this->owner, ['name' => 'Spring']);
        $campaigns->addCreative($campaign, $this->owner, ['headline' => 'Plant a tree']);
        $campaigns->submit($campaign, $this->owner);

        return $campaigns->approve($campaign, $this->admin, true);
    }

    public function test_a_purchase_fills_two_separate_balances_once(): void
    {
        $wallets = app(WalletService::class);
        $wallets->purchase($this->advertiser, $this->package(10, 2), $this->admin, 'BANK-1');

        $this->assertSame(10, $this->advertiser->balance(CreditType::Impression));
        $this->assertSame(2, $this->advertiser->balance(CreditType::SponsoredQuestion));
        $this->assertSame(1, LedgerEntry::query()->where('entry_type', 'payment')->count());

        $this->expectException(DomainActionException::class);
        $wallets->purchase($this->advertiser, $this->package(), $this->admin, 'BANK-1');
    }

    public function test_consumption_is_idempotent_and_never_overdraws(): void
    {
        $wallets = app(WalletService::class);
        $wallets->purchase($this->advertiser, $this->package(1, 0), $this->admin, 'BANK-2');

        $first = $wallets->consume($this->advertiser, CreditType::Impression, 1, 'impression:test-1');
        $again = $wallets->consume($this->advertiser, CreditType::Impression, 1, 'impression:test-1');

        $this->assertSame($first->id, $again->id);
        $this->assertSame(0, $this->advertiser->balance(CreditType::Impression));

        try {
            $wallets->consume($this->advertiser, CreditType::Impression, 1, 'impression:test-2');
            $this->fail('Expected InsufficientCredits');
        } catch (InsufficientCredits) {
            // The failed consumption leaves no ledger row behind.
            $this->assertFalse(LedgerEntry::query()->where('billing_reference', 'impression:test-2')->exists());
        }

        $this->assertSame(0, $this->advertiser->balance(CreditType::Impression));
        // Sponsored credits are a separate balance and cannot pay for impressions.
        $this->assertSame(0, $this->advertiser->balance(CreditType::SponsoredQuestion));
    }

    public function test_ledger_entries_cannot_be_changed_and_refunds_are_new_entries(): void
    {
        $wallets = app(WalletService::class);
        $wallets->purchase($this->advertiser, $this->package(5, 0), $this->admin, 'BANK-3');
        $entry = $wallets->consume($this->advertiser, CreditType::Impression, 1, 'impression:test-3');

        foreach ([fn () => $entry->update(['quantity' => 0]), fn () => $entry->delete()] as $change) {
            try {
                $change();
                $this->fail('Ledger entries must be immutable.');
            } catch (LogicException) {
            }
        }

        $wallets->reverse($entry, $this->admin, 'Bot traffic');

        $this->assertSame(5, $this->advertiser->balance(CreditType::Impression));
        $this->assertSame(1, LedgerEntry::query()->where('entry_type', 'reversal')->count());
        $this->assertSame(1, LedgerEntry::query()->where('entry_type', 'consumption')->count());
    }

    public function test_an_impression_is_billed_once_and_only_after_confirmation(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1]]);
        app(WalletService::class)->purchase($this->advertiser, $this->package(5, 0), $this->admin, 'BANK-4');
        $campaign = $this->activeCampaign();

        [$room, $gq, $host, $guest] = $this->runningGame();
        $ads = app(AdService::class);

        $delivery = $ads->offer($room, $gq, $host);
        $this->assertNotNull($delivery);
        $this->assertSame($delivery->id, $ads->offer($room, $gq, $host)->id);
        $this->assertSame(5, $this->advertiser->balance(CreditType::Impression), 'Offering an ad bills nothing.');

        $this->assertNull($ads->confirm($delivery->token, $guest), 'Another player cannot confirm the token.');

        $ads->confirm($delivery->token, $host);
        $ads->confirm($delivery->token, $host);

        $this->assertSame(1, Impression::query()->count());
        $this->assertSame(4, $this->advertiser->balance(CreditType::Impression));
        $this->assertSame($campaign->id, Impression::query()->value('campaign_id'));
    }

    public function test_expired_tokens_are_not_billed(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1]]);
        app(WalletService::class)->purchase($this->advertiser, $this->package(5, 0), $this->admin, 'BANK-5');
        $this->activeCampaign();
        [$room, $gq, $host] = $this->runningGame();

        $delivery = app(AdService::class)->offer($room, $gq, $host);
        $this->travel(11)->minutes();

        $this->assertNull(app(AdService::class)->confirm($delivery->token, $host));
        $this->assertSame(5, $this->advertiser->balance(CreditType::Impression));
    }

    public function test_campaign_stops_when_credits_run_out(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1]]);
        $campaign = $this->activeCampaign();
        [$room, $gq, $host] = $this->runningGame();

        // No impression credits: nothing is offered at all.
        $this->assertNull(app(AdService::class)->offer($room, $gq, $host));

        app(WalletService::class)->purchase($this->advertiser, $this->package(1, 0), $this->admin, 'BANK-6');
        $delivery = app(AdService::class)->offer($room, $gq, $host);
        app(WalletService::class)->consume($this->advertiser, CreditType::Impression, 1, 'impression:elsewhere');

        $this->assertNull(app(AdService::class)->confirm($delivery->token, $host));
        $this->assertSame(CampaignStatus::Exhausted, $campaign->fresh()->status);
    }

    public function test_a_sponsored_question_is_billed_once_per_room(): void
    {
        app(WalletService::class)->purchase($this->advertiser, $this->package(0, 3), $this->admin, 'BANK-7');
        $campaign = $this->activeCampaign();
        $this->publishQuestions(2);
        $sponsored = Question::factory()->published()->create(['category_id' => $this->category->id, 'difficulty' => 'easy']);
        app(SponsoredQuestionService::class)->assign($sponsored, $campaign, $this->admin);

        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $room = $this->room($host, ['question_count' => 3]);
        app(RoomService::class)->join($room, $guest);
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $host);

        $this->assertTrue($game->gameQuestions()->where('question_id', $sponsored->id)->exists(), 'The sponsored question is included.');

        // Play the whole game; ticking repeatedly must not bill again.
        for ($i = 0; $i < 12; $i++) {
            $this->travel(25)->seconds();
            $engine->tick($game->fresh());
            $engine->tick($game->fresh());
        }

        $this->assertSame(1, SponsoredDelivery::query()->count());
        $this->assertSame(2, $this->advertiser->balance(CreditType::SponsoredQuestion));
        $this->assertSame(2, SponsoredDelivery::query()->value('players_count'));
    }

    public function test_sponsored_questions_never_appear_in_normal_selection(): void
    {
        $campaign = $this->activeCampaign();
        $question = Question::factory()->published()->create(['category_id' => $this->category->id]);
        app(SponsoredQuestionService::class)->assign($question, $campaign, $this->admin);

        $ids = app(\App\Domain\Gameplay\QuestionSelector::class)->select('en', [$this->category->id], null, 5, []);

        $this->assertNotContains($question->id, $ids);
    }

    public function test_reconciliation_matches_events_with_the_ledger(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1]]);
        app(WalletService::class)->purchase($this->advertiser, $this->package(5, 0), $this->admin, 'BANK-8');
        $this->activeCampaign();
        [$room, $gq, $host] = $this->runningGame();
        app(AdService::class)->confirm(app(AdService::class)->offer($room, $gq, $host)->token, $host);

        $this->artisan('advertising:reconcile')->assertSuccessful();
        $this->assertTrue(app(ReconciliationService::class)->last()['ok']);

        // A consumption without an event is reported.
        app(WalletService::class)->consume($this->advertiser, CreditType::Impression, 1, 'impression:orphan');
        $report = app(ReconciliationService::class)->run();
        $this->assertFalse($report['ok']);
        $this->assertSame(['impression:orphan'], $report['entries_without_event']);
    }

    public function test_advertiser_dashboard_is_private_to_its_owner(): void
    {
        $this->activeCampaign();

        $this->actingAs($this->owner)->get(route('advertiser.dashboard'))->assertOk()->assertSee('Spring');
        $this->actingAs($this->player())->get(route('advertiser.dashboard'))->assertForbidden();

        $orphan = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $this->actingAs($orphan)->get(route('advertiser.dashboard'))->assertForbidden();
    }

    public function test_advertisers_create_and_submit_campaigns_from_the_dashboard(): void
    {
        Livewire::actingAs($this->owner)->test('pages::advertiser.dashboard')
            ->set('newName', 'Autumn')
            ->call('createCampaign')
            ->assertHasNoErrors();

        $campaign = Campaign::query()->where('name', 'Autumn')->firstOrFail();
        $this->assertSame(CampaignStatus::Draft, $campaign->status);

        Livewire::actingAs($this->owner)->test('pages::advertiser.dashboard')
            ->set('creativeFor', $campaign->id)
            ->set('headline', 'Recycle more')
            ->set('linkUrl', 'https://example.org')
            ->call('addCreative')
            ->call('submit', $campaign->id);

        $this->assertSame(CampaignStatus::PendingReview, $campaign->fresh()->status);
    }

    public function test_admins_manage_advertisers_packages_credits_and_review(): void
    {
        $user = User::factory()->create(['email' => 'ngo@example.org']);

        $page = Livewire::actingAs($this->admin)->test('pages::admin.advertising')
            ->set('advEmail', 'ngo@example.org')->set('advName', 'Blue NGO')->set('advType', 'ngo')
            ->call('createAdvertiser')->assertHasNoErrors()
            ->set('pkgName', 'Pack')->set('pkgImpressions', 100)->set('pkgSponsored', 1)->set('pkgPrice', '25.50')
            ->call('savePackage')->assertHasNoErrors();

        $advertiser = Advertiser::query()->where('user_id', $user->id)->firstOrFail();
        $package = AdvertisingPackage::query()->where('name', 'Pack')->firstOrFail();
        $this->assertTrue($user->fresh()->hasRole(UserRole::Advertiser->value));
        $this->assertSame(2550, $package->price_minor);

        $page->set('walletAdvertiser', $advertiser->id)->set('purchasePackage', $package->id)->set('paymentReference', 'CHQ-1')
            ->call('recordPurchase')
            ->set('walletAdvertiser', $advertiser->id)->set('adjustType', 'impression')->set('adjustQuantity', -10)->set('adjustNote', 'Goodwill correction')
            ->call('adjustCredits');

        $this->assertSame(90, $advertiser->balance(CreditType::Impression));

        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($this->advertiser, $this->owner, ['name' => 'Review me']);
        $campaigns->addCreative($campaign, $this->owner, ['headline' => 'Hello']);
        $campaigns->submit($campaign, $this->owner);

        $page->call('approve', $campaign->id);
        $this->assertSame(CampaignStatus::PendingReview, $campaign->fresh()->status, 'No approval without the policy tick.');

        $page->set('policyChecked.'.$campaign->id, true)->call('approve', $campaign->id);
        $this->assertSame(CampaignStatus::Active, $campaign->fresh()->status);
        $this->assertSame(1, $this->owner->notifications()->count());

        $page->set('tab', 'reconciliation')->call('reconcile')->assertSee(__('advertising.reconciled_ok'));
    }

    public function test_only_advertising_managers_reach_the_admin_screen(): void
    {
        $this->actingAs($this->admin)->get(route('admin.advertising'))->assertOk();
        $moderator = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Moderator->value));
        $this->actingAs($moderator)->get(route('admin.advertising'))->assertForbidden();
    }

    /**
     * @return array{0: \App\Domain\Gameplay\Models\Room, 1: \App\Domain\Gameplay\Models\GameQuestion, 2: User, 3: User}
     */
    private function runningGame(): array
    {
        $this->publishQuestions(3);
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $room = $this->room($host);
        app(RoomService::class)->join($room, $guest);
        $game = app(GameEngine::class)->start($room, $host);

        return [$room->fresh(), $game->gameQuestions()->where('position', 1)->firstOrFail(), $host, $guest];
    }
}

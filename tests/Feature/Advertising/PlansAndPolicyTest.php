<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\AdService;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\PolicyEnforcementService;
use App\Domain\Advertising\SponsoredQuestionService;
use App\Domain\Advertising\WalletService;
use App\Domain\Billing\Contracts\PaymentProvider;
use App\Domain\Billing\Drivers\ManualPaymentProvider;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentNotification;
use App\Domain\Billing\PaymentService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Tests\Feature\Gameplay\GameplayTestCase;

class PlansAndPolicyTest extends GameplayTestCase
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
        app(PlatformSettings::class)->update(['advertising' => ['every_n_questions' => 1]]);
        app(WalletService::class)->purchase($this->advertiser, AdvertisingPackage::query()->create(['name' => 'Essential', 'impression_credits' => 10, 'sponsored_question_credits' => 2, 'price_minor' => 1000, 'currency' => 'TND']), $this->admin, 'INV-1');
    }

    private function campaign(string $name = 'Spring'): Campaign
    {
        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($this->advertiser, $this->owner, ['name' => $name]);
        $campaigns->addCreative($campaign, $this->owner, ['headline' => 'Plant a tree']);
        $campaigns->submit($campaign, $this->owner);

        return $campaigns->approve($campaign, $this->admin);
    }

    /**
     * @return array{0: \App\Domain\Gameplay\Models\Room, 1: \App\Domain\Gameplay\Models\GameQuestion}
     */
    private function game(User $host, User $guest): array
    {
        $this->publishQuestions(3);
        $room = $this->room($host);
        app(RoomService::class)->join($room, $guest);
        $game = app(GameEngine::class)->start($room, $host);

        return [$room->fresh(), $game->gameQuestions()->where('position', 1)->firstOrFail()];
    }

    public function test_the_payment_provider_is_abstract_and_manual_by_default(): void
    {
        $this->assertInstanceOf(ManualPaymentProvider::class, app(PaymentProvider::class));
        $this->assertFalse(app(PaymentProvider::class)->supportsOnlineCheckout());
    }

    public function test_a_manual_payment_grants_the_paid_plan_once(): void
    {
        $player = $this->player();

        $payment = app(PaymentService::class)->grantManually($player, 3, 'CASH-1', 3000);
        $this->assertTrue($player->fresh()->hasPaidPlan());
        $until = $player->fresh()->plan_expires_at;

        app(PaymentService::class)->markPaid($payment);
        $this->assertEquals($until, $player->fresh()->plan_expires_at, 'Confirming twice does not extend twice.');

        $this->expectException(DomainActionException::class);
        app(PaymentService::class)->grantManually($player, 1, 'CASH-1');
    }

    public function test_plans_can_be_granted_from_the_command_line(): void
    {
        $player = $this->player();

        $this->artisan('plans:grant', ['email' => $player->email, 'reference' => 'BANK-9', '--months' => 2])->assertSuccessful();

        $this->assertTrue($player->fresh()->hasPaidPlan());
        $this->travel(3)->months();
        $this->assertFalse($player->fresh()->hasPaidPlan(), 'The plan ends after its months.');
    }

    public function test_verified_notifications_must_match_the_amount(): void
    {
        $player = $this->player();
        $payment = Payment::query()->create(['user_id' => $player->id, 'purpose' => 'player_plan', 'provider' => 'manual', 'provider_reference' => 'GW-1', 'amount_minor' => 500, 'currency' => 'TND', 'metadata' => ['months' => 1]]);

        $this->assertNull(app(PaymentService::class)->handle(new PaymentNotification('GW-1', PaymentStatus::Paid, 1, 'TND')));
        $this->assertFalse($player->fresh()->hasPaidPlan());

        app(PaymentService::class)->handle(new PaymentNotification('GW-1', PaymentStatus::Paid, 500, 'TND'));
        $this->assertSame(PaymentStatus::Paid, $payment->fresh()->status);
        $this->assertTrue($player->fresh()->hasPaidPlan());
    }

    public function test_paid_players_see_no_ads(): void
    {
        $this->campaign();
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        app(PaymentService::class)->grantManually($guest, 1, 'P-1');
        [$room, $gq] = $this->game($host, $guest);

        $this->assertNull(app(AdService::class)->offer($room, $gq, $guest));
        $this->assertNotNull(app(AdService::class)->offer($room, $gq, $host), 'Free players in the same room still get ads.');
    }

    public function test_a_paid_host_makes_the_whole_room_ad_free(): void
    {
        $campaign = $this->campaign();
        $sponsored = Question::factory()->published()->create(['category_id' => $this->category->id, 'difficulty' => 'easy']);
        app(SponsoredQuestionService::class)->assign($sponsored, $campaign, $this->admin);

        $host = $this->player('Host');
        $guest = $this->player('Guest');
        app(PaymentService::class)->grantManually($host, 1, 'P-2');
        [$room, $gq] = $this->game($host, $guest);

        $this->assertNull(app(AdService::class)->offer($room, $gq, $guest), 'Free guest in a paid host\'s room: no banner.');
        $this->assertFalse($room->game->gameQuestions()->where('question_id', $sponsored->id)->exists(), 'No sponsored question either.');
    }

    public function test_three_breaches_warn_then_call_then_suspend(): void
    {
        $policy = app(PolicyEnforcementService::class);

        $first = $policy->recordBreach($this->campaign('One'), $this->admin, 'Alcohol brand');
        $this->assertSame(1, $first->level);
        $this->assertSame('active', $this->advertiser->fresh()->status);

        $second = $policy->recordBreach($this->campaign('Two'), $this->admin, 'Mocking a belief');
        $this->assertSame(2, $second->level);
        $this->assertCount(1, $policy->callsDue());
        $policy->recordCall($second, $this->admin);
        $this->assertCount(0, $policy->callsDue());

        $running = $this->campaign('Three');
        $other = $this->campaign('Four');
        $policy->recordBreach($running, $this->admin, 'Gambling');

        $this->assertSame('suspended', $this->advertiser->fresh()->status);
        $this->assertSame(CampaignStatus::Rejected, $running->fresh()->status);
        $this->assertSame(CampaignStatus::Paused, $other->fresh()->status);
        $this->assertSame(3, $this->owner->notifications()->where('data->type', 'like', 'advertiser_policy_%')->count());

        // Credits are kept but frozen: no campaign of a suspended advertiser runs.
        $this->assertSame(10, $this->advertiser->balance(\App\Domain\Advertising\Enums\CreditType::Impression));
        $other->forceFill(['status' => CampaignStatus::Active])->save();
        $this->assertFalse(Campaign::query()->running()->whereKey($other->id)->exists());

        // The suspended advertiser can't create campaigns; an admin can reactivate.
        try {
            app(CampaignService::class)->create($this->advertiser->fresh(), $this->owner, ['name' => 'Five']);
            $this->fail('A suspended advertiser must not create campaigns.');
        } catch (DomainActionException) {
        }

        $policy->reactivate($this->advertiser->fresh(), $this->admin);
        $this->assertTrue(Campaign::query()->running()->whereKey($other->id)->exists());
    }

    public function test_admins_record_breaches_from_the_review_screen(): void
    {
        $campaign = $this->campaign();

        \Livewire\Livewire::actingAs($this->admin)->test('pages::admin.advertising')
            ->set('rejectNotes.'.$campaign->id, 'Not kids-friendly')
            ->call('breach', $campaign->id);

        $this->assertSame(CampaignStatus::Rejected, $campaign->fresh()->status);
        $this->assertSame(1, \App\Domain\Advertising\Models\PolicyStrike::query()->count());
    }

    public function test_terms_describe_the_room_rule_and_the_three_steps(): void
    {
        $this->get(route('terms'))
            ->assertOk()
            ->assertSee(__('terms.sections.accounts.paragraphs.1'))
            ->assertSee(__('terms.sections.advertising.paragraphs.2'));
    }
}

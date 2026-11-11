<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\AdService;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\AdDelivery;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Advertising\ReconciliationService;
use App\Domain\Advertising\WalletService;
use App\Domain\Billing\PaymentService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\PlatformSettings;
use Tests\Feature\Gameplay\GameplayTestCase;

/** Story 16 S3: results-page and waiting-room ads. */
class AdPlacementsTest extends GameplayTestCase
{
    private Advertiser $advertiser;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $this->advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => 'Blue NGO']);
        app(WalletService::class)->purchase($this->advertiser, AdvertisingPackage::query()->create(['name' => 'Essential', 'impression_credits' => 50, 'sponsored_question_credits' => 0, 'price_minor' => 1000, 'currency' => 'TND']), $admin, 'INV-P');

        $campaigns = app(CampaignService::class);
        $campaign = $campaigns->create($this->advertiser, $owner, ['name' => 'Seas', 'max_per_player_per_game' => 10]);
        $campaigns->addCreative($campaign, $owner, ['headline' => 'Protect the sea']);
        $campaigns->submit($campaign, $owner);
        $campaigns->approve($campaign, $admin, true);
    }

    /**
     * @return array{0: Room, 1: User, 2: User}
     */
    private function lobby(): array
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $this->publishQuestions(1);
        $room = $this->room($host, ['question_count' => 1]);
        app(RoomService::class)->join($room, $guest);

        return [$room->fresh(), $host, $guest];
    }

    private function finish(Room $room, User $host, User $guest): Room
    {
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $host);
        $question = Question::find($game->gameQuestions()->first()->question_id);
        $engine->submitAnswer($game, $host, $question->correct_choice_id);
        $engine->submitAnswer($game, $guest, $question->correct_choice_id);
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $engine->tick($game->fresh());

        return $room->fresh();
    }

    public function test_the_waiting_room_shows_one_ad_per_player_and_room(): void
    {
        [$room, $host] = $this->lobby();
        $ads = app(AdService::class);

        $first = $ads->offerLobby($room, $host);
        $this->assertSame('lobby', $first->placement);
        $this->assertNull($first->game_question_id);
        $this->assertSame($first->id, $ads->offerLobby($room, $host)->id, 'Refreshing shows the same delivery.');

        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk()->assertSee('Protect the sea');
    }

    public function test_the_results_page_shows_an_ad_billed_once_after_confirmation(): void
    {
        [$room, $host, $guest] = $this->lobby();
        $room = $this->finish($room, $host, $guest);

        $this->actingAs($host)->get(route('rooms.results', $room))->assertOk()->assertSee('Protect the sea');

        $delivery = AdDelivery::query()->where('placement', 'post_game')->where('player_id', $host->id)->firstOrFail();
        $this->assertSame(50, $this->advertiser->balance(CreditType::Impression), 'Showing the page bills nothing.');

        app(AdService::class)->confirm($delivery->token, $host);
        app(AdService::class)->confirm($delivery->token, $host);

        $impression = Impression::query()->where('delivery_id', $delivery->id)->sole();
        $this->assertSame('post_game', $impression->placement);
        $this->assertSame('en', $impression->language);
        $this->assertSame($this->category->id, $impression->category_id);
        $this->assertSame(49, $this->advertiser->balance(CreditType::Impression));
    }

    public function test_placements_can_be_switched_off(): void
    {
        app(PlatformSettings::class)->update(['advertising' => ['lobby_enabled' => false, 'post_game_enabled' => false]]);
        [$room, $host, $guest] = $this->lobby();

        $this->assertNull(app(AdService::class)->offerLobby($room, $host));
        $room = $this->finish($room, $host, $guest);
        $this->assertNull(app(AdService::class)->offerPostGame($room, $host));
    }

    public function test_paid_accounts_and_their_rooms_get_no_placement_ads(): void
    {
        [$room, $host, $guest] = $this->lobby();
        app(PaymentService::class)->grantManually($host, 1, 'CASH-H');

        $this->assertNull(app(AdService::class)->offerLobby($room, $host));
        $this->assertNull(app(AdService::class)->offerLobby($room, $guest), 'Paid host: the whole room is ad-free.');
    }

    public function test_reconciliation_covers_the_new_placements(): void
    {
        [$room, $host] = $this->lobby();
        app(AdService::class)->confirm(app(AdService::class)->offerLobby($room, $host)->token, $host);

        $this->assertTrue(app(ReconciliationService::class)->run()['ok']);
    }
}

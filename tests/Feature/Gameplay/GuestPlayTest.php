<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\GuestPlayers;
use App\Domain\Gameplay\LeaderboardService;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Models\User;
use Livewire\Livewire;

/**
 * Playing and hosting need no account (nickname-only guest players); account
 * pages still do.
 */
class GuestPlayTest extends GameplayTestCase
{
    public function test_guest_players_can_open_their_room(): void
    {
        $room = $this->room($this->player());

        Livewire::test('pages::rooms.join')->set('nickname', 'Sami')->set('code', $room->code)->call('join');

        $this->get(route('rooms.show', $room))->assertOk();
    }

    public function test_guest_players_can_host_a_room(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $room = $this->room($guest);

        $this->assertSame($guest->id, $room->host_id);
        $this->assertTrue(app(RoomService::class)->isMember($room, $guest));
    }

    public function test_visitor_hosts_a_room_with_a_nickname(): void
    {
        $this->assertGuest();

        Livewire::test('pages::rooms.create')
            ->set('nickname', 'Sami')
            ->set('name', 'Friday quiz')
            ->set('categoryIds', [$this->category->id])
            ->call('create')
            ->assertHasNoErrors();

        $guest = User::query()->where('is_guest', true)->sole();
        $room = Room::query()->sole();

        $this->assertSame('Sami', $guest->name);
        $this->assertSame($guest->id, $room->host_id);
        $this->assertAuthenticatedAs($guest);
        $this->assertTrue(app(RoomService::class)->isMember($room, $guest));
    }

    public function test_visitor_must_give_a_nickname_to_host(): void
    {
        Livewire::test('pages::rooms.create')
            ->set('nickname', '')
            ->set('name', 'Friday quiz')
            ->set('categoryIds', [$this->category->id])
            ->call('create')
            ->assertHasErrors(['nickname']);

        $this->assertSame(0, User::query()->withTrashed()->where('is_guest', true)->count());
        $this->assertSame(0, Room::query()->count());
        $this->assertGuest();
    }

    public function test_failed_hosting_leaves_no_guest_behind(): void
    {
        Livewire::test('pages::rooms.create')
            ->set('nickname', 'Sami')
            ->set('name', 'Friday quiz')
            ->set('categoryIds', [])
            ->call('create')
            ->assertHasErrors(['category_ids']);

        $this->assertSame(0, User::query()->withTrashed()->where('is_guest', true)->count());
        $this->assertSame(0, Room::query()->count());
        $this->assertGuest();
    }

    public function test_visitors_can_open_the_host_page_without_an_account(): void
    {
        $this->get(route('rooms.create'))->assertOk()
            ->assertSee('id="host-nickname"', false);
    }

    public function test_visitor_joining_from_the_host_page_is_sent_to_the_join_page(): void
    {
        Livewire::test('pages::rooms.create')
            ->set('code', 'ABC123')
            ->call('join')
            ->assertRedirect(route('rooms.join', ['code' => 'ABC123']));

        $this->assertGuest();
    }

    public function test_guest_players_are_sent_to_sign_in_for_account_pages(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $this->actingAs($guest)->get(route('rooms.history'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($guest)->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_guests_who_host_a_room_are_kept_when_old_guests_are_pruned(): void
    {
        $host = app(GuestPlayers::class)->create('Host');
        $host->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();
        $room = $this->room($host);

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertNotNull(User::withTrashed()->find($host->id));
        $this->assertNotNull(Room::query()->find($room->id));
    }

    public function test_guest_players_can_open_sign_up_and_sign_in(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $this->actingAs($guest)->get(route('register'))->assertRedirect(route('register'));
        $this->assertGuest();
        $this->get(route('register'))->assertOk();
    }

    public function test_registered_players_still_create_rooms(): void
    {
        $this->actingAs($this->player())->get(route('rooms.create'))->assertOk();
    }

    public function test_joining_a_closed_room_leaves_no_guest_behind(): void
    {
        $room = $this->room($this->player(), ['max_players' => 2]);
        app(RoomService::class)->join($room, $this->player());

        Livewire::test('pages::rooms.join')->set('nickname', 'Sami')->set('code', $room->code)->call('join')
            ->assertHasErrors('code');

        $this->assertSame(0, User::query()->withTrashed()->where('is_guest', true)->count());
        $this->assertGuest();
    }

    public function test_guests_stay_off_the_leaderboard_and_profiles(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $this->assertTrue(app(LeaderboardService::class)->top('all')->where('user_id', $guest->id)->isEmpty());
        $this->actingAs($this->player())->get(route('players.show', $guest))->assertNotFound();
    }

    public function test_old_guests_are_pruned_and_accounts_are_kept(): void
    {
        $old = app(GuestPlayers::class)->create('Old');
        $old->forceFill(['created_at' => now()->subDays(8)])->saveQuietly();
        $recent = app(GuestPlayers::class)->create('Recent');
        $account = $this->player();
        $account->forceFill(['created_at' => now()->subYear()])->saveQuietly();

        $this->artisan('model:prune', ['--model' => [User::class]])->assertSuccessful();

        $this->assertNull(User::withTrashed()->find($old->id));
        $this->assertNotNull(User::find($recent->id));
        $this->assertNotNull(User::find($account->id));
    }
}

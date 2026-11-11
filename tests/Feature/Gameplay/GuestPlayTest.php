<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\GuestPlayers;
use App\Domain\Gameplay\LeaderboardService;
use App\Domain\Gameplay\RoomService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

/**
 * Playing needs no account (nickname-only guest players); creating a room
 * and account pages do.
 */
class GuestPlayTest extends GameplayTestCase
{
    public function test_guest_players_can_open_their_room(): void
    {
        $room = $this->room($this->player());

        Livewire::test('pages::rooms.join')->set('nickname', 'Sami')->set('code', $room->code)->call('join');

        $this->get(route('rooms.show', $room))->assertOk();
    }

    public function test_guest_players_cannot_create_rooms(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $this->assertTrue($guest->isGuest());
        $this->assertTrue($guest->can('play games'));

        $this->expectException(AuthorizationException::class);
        $this->room($guest);
    }

    public function test_guest_players_are_sent_to_sign_in_for_account_pages(): void
    {
        $guest = app(GuestPlayers::class)->create('Sami');

        $this->actingAs($guest)->get(route('rooms.create'))->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($guest)->get(route('dashboard'))->assertRedirect(route('login'));
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

<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\GuestPlayers;
use App\Domain\Gameplay\RoomService;
use Livewire\Livewire;

/**
 * The "Play" call to action: join a room or host one, then share the room
 * (QR code, link, WhatsApp, Messenger).
 */
class PlayStartTest extends GameplayTestCase
{
    public function test_visitors_choose_between_joining_and_hosting(): void
    {
        $this->get(route('play'))->assertOk()
            ->assertSee(__('play.start.join_title'))
            ->assertSee(__('play.start.host_title'))
            ->assertSee('action="'.route('rooms.join').'"', false)
            ->assertSee('href="'.route('rooms.create').'"', false)
            ->assertSee(__('play.start.host_needs_account'));
    }

    public function test_registered_players_are_not_told_to_create_an_account(): void
    {
        $this->actingAs($this->player())->get(route('play'))->assertOk()
            ->assertSee(__('play.start.host_title'))
            ->assertDontSee(__('play.start.host_needs_account'));
    }

    public function test_guest_players_see_that_hosting_needs_an_account(): void
    {
        $this->actingAs(app(GuestPlayers::class)->create('Sami'))->get(route('play'))->assertOk()
            ->assertSee(__('play.start.host_needs_account'));
    }

    public function test_play_buttons_open_the_chooser_for_everyone(): void
    {
        $this->get(route('home'))->assertSee('href="'.route('play').'"', false);
        $this->actingAs($this->player())->get(route('home'))->assertSee('href="'.route('play').'"', false);
    }

    public function test_hosting_sends_visitors_and_guest_players_to_the_sign_in_and_back(): void
    {
        $this->get(route('rooms.create'))->assertRedirect(route('login'));

        $this->actingAs(app(GuestPlayers::class)->create('Sami'))->get(route('rooms.create'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('url.intended', route('rooms.create'));
        $this->assertGuest();
    }

    public function test_the_waiting_room_offers_qr_code_link_whatsapp_and_messenger(): void
    {
        $host = $this->player();
        $room = $this->room($host);
        $links = app(RoomService::class)->shareLinks($room);

        $this->assertStringStartsWith('https://wa.me/?text=', $links['whatsapp']);
        $this->assertStringContainsString(rawurlencode($room->invitationUrl()), $links['whatsapp']);
        $this->assertSame('fb-messenger://share/?link='.rawurlencode($room->invitationUrl()), $links['messenger']);

        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk()
            ->assertSee(__('play.room.qr_label'))
            ->assertSee($room->invitationUrl())
            ->assertSee(e($links['whatsapp']), false)
            ->assertSee(e($links['messenger']), false);
    }

    public function test_the_join_page_passes_an_open_room_on_through_the_same_apps(): void
    {
        $room = $this->room($this->player());
        $links = app(RoomService::class)->invitationForCode($room->code)['links'];

        $this->get(route('rooms.join', ['code' => $room->code]))->assertOk()
            ->assertSee(e($links['whatsapp']), false)
            ->assertSee(e($links['messenger']), false);
    }

    public function test_public_rooms_are_listed_for_any_player_and_private_ones_are_not(): void
    {
        $host = $this->player('Host');
        $public = $this->room($host, ['name' => 'Open to all', 'visibility' => 'public']);
        $this->room($host, ['name' => 'Friends only', 'visibility' => 'private']);

        // Visitors without an account see the public room on the join page.
        $this->get(route('rooms.join'))->assertOk()
            ->assertSee(__('play.lobby.open_rooms'))
            ->assertSee('Open to all')
            ->assertSee('href="'.route('rooms.invitation', $public->code).'"', false)
            ->assertDontSee('Friends only');

        Livewire::test('rooms.open-rooms')->assertSee('Open to all')->assertDontSee('Friends only');
    }

    public function test_the_host_chooses_between_a_public_and_a_friends_only_room(): void
    {
        $this->publishQuestions(10);
        $host = $this->player();

        $page = Livewire::actingAs($host)->test('pages::rooms.create')
            ->assertSee(__('play.create.visibility_private_hint'))
            ->set('isPrivate', false)
            ->assertSee(__('play.create.visibility_public_hint'));

        $page->set('categoryIds', [$this->category->id])->call('create')->assertHasNoErrors();

        $this->assertSame(1, app(RoomService::class)->openRooms()->count());
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\RoomService;
use App\Models\User;
use App\Support\QrCode\QrCode;
use InvalidArgumentException;
use Livewire\Livewire;

/**
 * Invitation by QR code: the waiting room shows the code, scanning it opens
 * /join/{code}, where visitors pick a name (or use their account) and join.
 */
class RoomInvitationTest extends GameplayTestCase
{
    public function test_waiting_room_shows_the_invitation_as_a_qr_code(): void
    {
        $host = $this->player();
        $room = $this->room($host);
        $qr = app(RoomService::class)->invitationQr($room);

        $this->assertSame(QrCode::encode($room->invitationUrl())->svgPath(), $qr['path']);

        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk()
            ->assertSee(__('play.room.qr_hint'))
            ->assertSee('d="'.$qr['path'].'"', false);
    }

    public function test_visitors_pick_any_name_on_the_invitation_page_and_join(): void
    {
        $room = $this->room($this->player('Host'));

        $this->get(route('rooms.invitation', $room->code))->assertOk()
            ->assertSee($room->name)
            ->assertSee(__('play.invitation.name_title'))
            ->assertSee('id="nickname"', false);

        Livewire::test('pages::rooms.invitation', ['code' => $room->code])->call('join')->assertHasErrors('nickname');
        $this->assertSame(0, User::query()->where('is_guest', true)->count());

        Livewire::test('pages::rooms.invitation', ['code' => $room->code])->set('nickname', '  Sami   B ')->call('join')
            ->assertRedirect(route('rooms.show', $room));

        $guest = User::query()->where('is_guest', true)->sole();
        $this->assertSame('Sami B', $guest->name);
        $this->assertAuthenticatedAs($guest);
        $this->assertTrue(app(RoomService::class)->isMember($room, $guest));
    }

    public function test_signed_in_players_see_their_account_and_join_when_ready(): void
    {
        $room = $this->room($this->player('Host'));
        $player = $this->player('Leila');

        $this->actingAs($player)->get(route('rooms.invitation', $room->code))->assertOk()
            ->assertSee('Leila')
            ->assertSee(__('play.invitation.playing_as'))
            ->assertDontSee('id="nickname"', false);

        // Opening the link does not join yet.
        $this->assertFalse(app(RoomService::class)->isMember($room, $player));

        Livewire::actingAs($player)->test('pages::rooms.invitation', ['code' => $room->code])->call('join')
            ->assertRedirect(route('rooms.show', $room));

        $this->assertTrue(app(RoomService::class)->isMember($room, $player));
        $this->assertSame(0, User::query()->where('is_guest', true)->count());

        // Scanning again goes straight back to the room.
        $this->actingAs($player)->get(route('rooms.invitation', $room->code))->assertRedirect(route('rooms.show', $room));
    }

    public function test_visitors_can_sign_in_and_come_back_to_the_invitation(): void
    {
        $room = $this->room($this->player('Host'));

        Livewire::test('pages::rooms.invitation', ['code' => $room->code])->call('signIn')->assertRedirect(route('login'));

        $this->assertSame(route('rooms.invitation', $room->code), session('url.intended'));
    }

    public function test_a_full_room_explains_the_problem_and_keeps_no_guest(): void
    {
        $room = $this->room($this->player('Host'), ['max_players' => 2]);
        app(RoomService::class)->join($room, $this->player());

        Livewire::test('pages::rooms.invitation', ['code' => $room->code])->set('nickname', 'Sami')->call('join')
            ->assertHasErrors('nickname')
            ->assertSee(__('play.errors.room_full', ['max' => 2]));

        $this->assertSame(0, User::query()->withTrashed()->where('is_guest', true)->count());
        $this->assertGuest();

        Livewire::actingAs($this->player('Leila'))->test('pages::rooms.invitation', ['code' => $room->code])->call('join')
            ->assertHasErrors('nickname');
    }

    public function test_players_who_may_not_play_cannot_use_an_invitation(): void
    {
        $room = $this->room($this->player('Host'));
        $outsider = User::factory()->create(); // no role, so no "play games" permission

        $this->actingAs($outsider)->get(route('rooms.invitation', $room->code))->assertForbidden();
    }

    public function test_joining_from_an_invitation_is_rate_limited(): void
    {
        config(['fakkroun.form_throttle.join-room' => [2, 60]]);
        $room = $this->room($this->player('Host'));

        foreach ([1, 2] as $ignored) {
            Livewire::test('pages::rooms.invitation', ['code' => $room->code])->call('join')->assertHasErrors('nickname');
        }

        Livewire::test('pages::rooms.invitation', ['code' => $room->code])->set('nickname', 'Sami')->call('join')
            ->assertHasErrors('nickname')
            ->assertNoRedirect();

        $this->assertSame(0, User::query()->where('is_guest', true)->count());
    }

    public function test_qr_encoder_builds_a_valid_symbol(): void
    {
        // Reference symbol for "HELLO" (version 1, level M, mask 0), identical in two other encoders.
        $qr = QrCode::encode('HELLO', 0);

        $this->assertSame(1, $qr->version);
        $this->assertSame(21, $qr->size());
        $this->assertSame(29, $qr->viewBoxSize());
        $this->assertSame([
            '111111100111001111111',
            '100000101011001000001',
            '101110100010001011101',
            '101110100011001011101',
            '101110101010101011101',
            '100000100110101000001',
            '111111101010101111111',
            '000000000101100000000',
            '101010100101000010010',
            '101010000110001000010',
            '000001100110100011111',
            '101011010110001000010',
            '011001110110101010100',
            '000000001011010100110',
            '111111100001011100111',
            '100000100011110110000',
            '101110101011011100111',
            '101110100100001100110',
            '101110101010100010101',
            '100000100110001010010',
            '111111101010101100111',
        ], array_map(fn (array $row): string => implode('', array_map(fn (bool $dark): string => $dark ? '1' : '0', $row)), $qr->modules()));

        $this->assertStringStartsWith('M4 4h7v1h-7z', $qr->svgPath());
    }

    public function test_qr_encoder_grows_with_the_text_and_rejects_what_does_not_fit(): void
    {
        $this->assertSame(1, QrCode::encode(str_repeat('a', 14))->version);
        $this->assertSame(2, QrCode::encode(str_repeat('a', 15))->version);
        $this->assertSame(9, QrCode::encode(str_repeat('a', 180))->version);
        $this->assertContains(QrCode::encode('https://fakkroun.test/join/7KQ2AB')->mask, range(0, 7));

        $this->expectException(InvalidArgumentException::class);
        QrCode::encode(str_repeat('a', 181));
    }
}

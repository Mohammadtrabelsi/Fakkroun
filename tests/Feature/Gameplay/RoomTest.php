<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\RoomCodeGenerator;
use App\Domain\Gameplay\RoomService;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

class RoomTest extends GameplayTestCase
{
    public function test_host_creates_a_room_with_a_code_and_is_its_first_player(): void
    {
        $host = $this->player('Host');
        $room = $this->room($host);

        $this->assertMatchesRegularExpression('/^['.RoomCodeGenerator::ALPHABET.']{6}$/', $room->code);
        $this->assertSame(RoomStatus::Waiting, $room->status);
        $this->assertTrue(app(RoomService::class)->isMember($room, $host));
        $this->assertSame(route('rooms.invitation', $room->code), $room->invitationUrl());
        $this->assertFalse(app(RoomService::class)->isReady($room));
    }

    public function test_room_settings_respect_platform_limits(): void
    {
        app(PlatformSettings::class)->update(['game' => ['max_players' => 6]]);

        $this->expectException(ValidationException::class);
        $this->room($this->player(), ['max_players' => 7]);
    }

    public function test_concurrent_room_cap_is_enforced(): void
    {
        app(PlatformSettings::class)->update(['game' => ['max_concurrent_rooms' => 1]]);
        $this->room($this->player());

        $this->expectException(DomainActionException::class);
        $this->room($this->player());
    }

    public function test_players_join_by_code_and_the_room_becomes_ready(): void
    {
        $room = $this->room($this->player());
        $guest = $this->player('Guest');
        $service = app(RoomService::class);

        $found = $service->findByCode(' '.strtolower($room->code).' ');
        $service->join($found, $guest);
        $service->join($found, $guest); // second tab / rescan: no-op

        $this->assertSame(2, $service->playerCount($room));
        $this->assertTrue($service->isReady($room));
    }

    public function test_full_rooms_reject_new_players_with_a_clear_message(): void
    {
        $room = $this->room($this->player(), ['max_players' => 2]);
        app(RoomService::class)->join($room, $this->player());

        try {
            app(RoomService::class)->join($room, $this->player());
            $this->fail('Joined a full room.');
        } catch (DomainActionException $e) {
            $this->assertSame(__('play.errors.room_full', ['max' => 2]), $e->getMessage());
        }
    }

    public function test_invitation_link_sends_visitors_to_the_join_page_then_joins(): void
    {
        $room = $this->room($this->player());
        $guest = $this->player();

        $this->get(route('rooms.invitation', $room->code))->assertRedirect(route('rooms.join', ['code' => $room->code]));

        $this->actingAs($guest)->get(route('rooms.invitation', $room->code))->assertRedirect(route('rooms.show', $room));
        $this->assertTrue(app(RoomService::class)->isMember($room, $guest));
    }

    public function test_invitation_with_an_unknown_code_explains_the_problem(): void
    {
        $this->actingAs($this->player())->get(route('rooms.invitation', 'ZZZZZZ'))
            ->assertRedirect(route('rooms.join'))
            ->assertSessionHas('error', __('play.errors.room_not_found'));
    }

    public function test_join_page_validates_the_code(): void
    {
        $room = $this->room($this->player());
        $guest = $this->player();

        Livewire::actingAs($guest)->test('rooms.join-form')->set('code', 'NOPE99')->call('join')->assertHasErrors('code');
        Livewire::actingAs($guest)->test('rooms.join-form')->set('code', $room->code)->call('join')->assertRedirect(route('rooms.show', $room));
    }

    public function test_join_page_is_a_public_front_office_page(): void
    {
        $this->get(route('rooms.join'))->assertOk()->assertSee(__('play.join.title'));
    }

    public function test_visitor_joining_with_a_code_needs_a_nickname_not_an_account(): void
    {
        $room = $this->room($this->player());

        Livewire::test('pages::rooms.join')->set('code', $room->code)->call('join')->assertHasErrors('nickname');
        Livewire::test('pages::rooms.join')->set('nickname', 'Sami')->set('code', 'NOPE99')->call('join')->assertHasErrors('code');
        $this->assertSame(0, User::query()->where('is_guest', true)->count());

        Livewire::test('pages::rooms.join')->set('nickname', 'Sami')->set('code', $room->code)->call('join')
            ->assertRedirect(route('rooms.show', $room));

        $guest = User::query()->where('is_guest', true)->sole();
        $this->assertSame('Sami', $guest->name);
        $this->assertAuthenticatedAs($guest);
        $this->assertTrue(app(RoomService::class)->isMember($room, $guest));
    }

    public function test_host_leaving_the_lobby_hands_over_or_cancels(): void
    {
        $host = $this->player();
        $other = $this->player();
        $room = $this->room($host);
        app(RoomService::class)->join($room, $other);

        app(RoomService::class)->leave($room, $host);
        $this->assertSame($other->id, $room->fresh()->host_id);

        app(RoomService::class)->leave($room, $other);
        $this->assertSame(RoomStatus::Cancelled, $room->fresh()->status);
    }

    public function test_users_without_play_permission_are_refused(): void
    {
        $this->expectException(AuthorizationException::class);
        $this->room(User::factory()->create());
    }

    public function test_room_pages_render(): void
    {
        $host = $this->player();
        $room = $this->room($host);

        $this->actingAs($host)->get(route('rooms.create'))->assertOk();
        $this->actingAs($host)->get(route('rooms.show', $room))->assertOk()->assertSee($room->code)->assertSee($room->invitationUrl(), false);
    }
}

<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use Livewire\Livewire;

class PlayScreensTest extends GameplayTestCase
{
    public function test_lobby_creates_a_room_from_chips_and_segmented_controls(): void
    {
        $host = $this->player('Host');
        $this->publishQuestions(3);

        Livewire::actingAs($host)->test('pages::rooms.create')
            ->assertSet('categoryIds', [$this->category->id])
            ->set('difficulty', 'easy')
            ->set('questionCount', 10)
            ->set('secondsPerQuestion', 30)
            ->set('language', 'fr')
            ->set('isPrivate', false)
            ->call('create')
            ->assertHasNoErrors();

        $room = Room::query()->latest('id')->firstOrFail();
        $this->assertSame('public', $room->visibility);
        $this->assertSame([$this->category->id], $room->setting('category_ids'));
        $this->assertSame(30, $room->setting('seconds_per_question'));
        $this->assertSame('fr', $room->setting('language'));
    }

    public function test_lobby_requires_a_category(): void
    {
        Livewire::actingAs($this->player())->test('pages::rooms.create')
            ->call('toggleCategory', $this->category->id)
            ->assertSet('categoryIds', [])
            ->call('create')
            ->assertHasErrors('category_ids');
    }

    public function test_only_public_waiting_rooms_are_listed_as_open(): void
    {
        $public = $this->room($this->player(), ['visibility' => 'public', 'name' => 'Open science night']);
        $this->room($this->player(), ['visibility' => 'private', 'name' => 'Secret family quiz']);

        $this->assertSame([$public->id], app(RoomService::class)->openRooms()->pluck('id')->all());

        $this->actingAs($this->player())->get(route('rooms.create'))
            ->assertOk()
            ->assertSee('Open science night')
            ->assertDontSee('Secret family quiz');
    }

    public function test_lobby_joins_by_code_and_prefills_it_from_the_url(): void
    {
        $room = $this->room($this->player());
        $guest = $this->player();

        Livewire::withQueryParams(['code' => strtolower($room->code)])->actingAs($guest)
            ->test('pages::rooms.create')
            ->assertSet('code', strtolower($room->code))
            ->call('join')
            ->assertRedirect(route('rooms.show', $room));
    }

    private function playToTheEnd(): Room
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $this->publishQuestions(2);
        $room = $this->room($host, ['question_count' => 2]);
        app(RoomService::class)->join($room, $guest);
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $host);

        foreach ([1, 2] as $n) {
            $gq = $game->gameQuestions()->where('position', $game->fresh()->current_position)->first();
            $question = Question::withTrashed()->find($gq->question_id);
            $engine->submitAnswer($game, $host, $question->correct_choice_id);
            $engine->submitAnswer($game, $guest, $question->choices()->where('id', '!=', $question->correct_choice_id)->value('id'));
            $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
            $engine->tick($game);
        }

        return $room->fresh();
    }

    public function test_results_show_podium_standings_and_recap(): void
    {
        $room = $this->playToTheEnd();
        $host = $room->host;

        $recap = app(GameEngine::class)->recap($room->game, $host);
        $this->assertCount(2, $recap);
        $this->assertTrue($recap[0]['correct']);
        $this->assertNotNull($recap[0]['correct_text']);

        $this->actingAs($host)->get(route('rooms.results', $room))
            ->assertOk()
            ->assertSee(__('play.results2.recap'))
            ->assertSee(__('play.results2.got_right', ['right' => 2, 'total' => 2]))
            ->assertSee('Host');
    }

    public function test_results_are_private_to_the_room_and_wait_for_the_end(): void
    {
        $room = $this->playToTheEnd();

        $this->actingAs($this->player())->get(route('rooms.results', $room))->assertForbidden();

        $waiting = $this->room($this->player());
        $this->actingAs($waiting->host)->get(route('rooms.results', $waiting))->assertRedirect(route('rooms.show', $waiting));
    }

    public function test_play_again_opens_a_new_room_with_the_same_settings(): void
    {
        $room = $this->playToTheEnd();

        Livewire::actingAs($room->host)->test('pages::rooms.results', ['room' => $room])->call('playAgain');

        $new = Room::query()->latest('id')->firstOrFail();
        $this->assertNotSame($room->id, $new->id);
        $this->assertNotSame($room->code, $new->code);
        $this->assertSame($room->settings['category_ids'], $new->settings['category_ids']);
        $this->assertSame($room->host_id, $new->host_id);
    }

    public function test_state_tells_who_answered_but_never_what(): void
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $this->publishQuestions(3);
        $room = $this->room($host);
        app(RoomService::class)->join($room, $guest);
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $host);
        $gq = $game->gameQuestions()->where('position', 1)->first();
        $engine->submitAnswer($game, $guest, Question::find($gq->question_id)->correct_choice_id);

        $state = $engine->state($game, $host);

        $this->assertSame([$guest->id], $state['answered_user_ids']);
        $this->assertNull($state['my_choice_id']);
        $this->assertNull($state['reveal']);
        $this->assertStringNotContainsString('correct_choice_id', json_encode($state));
    }
}

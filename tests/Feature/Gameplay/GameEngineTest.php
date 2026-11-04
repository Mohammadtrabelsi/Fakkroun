<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

class GameEngineTest extends GameplayTestCase
{
    private User $host;

    private User $guest;

    private Room $room;

    private GameEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = app(GameEngine::class);
        $this->host = $this->player('Host');
        $this->guest = $this->player('Guest');
        $this->publishQuestions(5);
    }

    private function openRoom(array $overrides = []): Room
    {
        $this->room = $this->room($this->host, $overrides);
        app(RoomService::class)->join($this->room, $this->guest);

        return $this->room;
    }

    private function current(Game $game): GameQuestion
    {
        return GameQuestion::query()->where('game_id', $game->id)->where('position', $game->fresh()->current_position)->firstOrFail();
    }

    /**
     * @return array{correct: int, wrong: int}
     */
    private function choicesOf(GameQuestion $gq): array
    {
        $question = Question::withTrashed()->with('choices')->find($gq->question_id);
        $wrong = $question->choices->firstWhere('id', '!=', $question->correct_choice_id);

        return ['correct' => $question->correct_choice_id, 'wrong' => $wrong->id];
    }

    public function test_only_the_host_starts_and_only_with_enough_players(): void
    {
        $room = $this->room($this->host);

        try {
            $this->engine->start($room, $this->host);
            $this->fail('Started alone.');
        } catch (DomainActionException $e) {
            $this->assertSame(__('play.errors.not_enough_players', ['min' => 2]), $e->getMessage());
        }

        app(RoomService::class)->join($room, $this->guest);

        try {
            $this->engine->start($room, $this->guest);
            $this->fail('A guest started the game.');
        } catch (AuthorizationException) {
        }

        $game = $this->engine->start($room, $this->host);
        $this->assertSame(RoomStatus::InProgress, $room->fresh()->status);
        $this->assertSame(3, $game->total_questions);
        $this->assertNotNull($this->current($game)->deadline_at);
    }

    public function test_a_game_cannot_be_started_twice(): void
    {
        $room = $this->openRoom();
        $this->engine->start($room, $this->host);

        $this->expectException(DomainActionException::class);
        $this->engine->start($room->fresh(), $this->host);
    }

    public function test_new_players_cannot_join_a_running_game_but_members_can_rejoin(): void
    {
        $room = $this->openRoom();
        $this->engine->start($room, $this->host);

        app(RoomService::class)->leave($room, $this->guest);
        app(RoomService::class)->join($room, $this->guest);
        $this->assertTrue(app(RoomService::class)->isMember($room, $this->guest));

        $this->expectException(DomainActionException::class);
        app(RoomService::class)->join($room, $this->player());
    }

    public function test_questions_come_only_from_the_pool_for_the_room_language_and_topics(): void
    {
        $otherTopic = Category::factory()->create();
        $this->publishQuestions(5, 'easy', $otherTopic);
        Question::factory()->count(3)->pending()->create(['category_id' => $this->category->id]);
        Question::factory()->count(3)->published()->create(['category_id' => $this->category->id, 'origin_language' => 'fr']);

        $game = $this->engine->start($this->openRoom(['question_count' => 10]), $this->host);

        // Only the five published English questions of the chosen topic qualify.
        $this->assertSame(5, $game->total_questions);
        $questionIds = GameQuestion::query()->where('game_id', $game->id)->pluck('question_id');
        $this->assertSame(5, $questionIds->unique()->count());
        $this->assertTrue(Question::query()->whereIn('id', $questionIds)->get()->every(fn (Question $q) => $q->category_id === $this->category->id && $q->origin_language === 'en' && $q->isPublished()));
    }

    public function test_a_room_without_playable_questions_cannot_start(): void
    {
        $this->expectException(DomainActionException::class);
        $this->engine->start($this->openRoom(['language' => 'ar']), $this->host);
    }

    public function test_the_correct_answer_is_hidden_until_the_question_closes(): void
    {
        $game = $this->engine->start($this->openRoom(), $this->host);

        $state = $this->engine->state($game, $this->guest);
        $this->assertSame('question', $state['phase']);
        $this->assertNull($state['reveal']);
        $this->assertStringNotContainsString('correct_choice_id', json_encode($state));
        $this->assertCount(4, $state['question']['choices']);
    }

    public function test_one_answer_per_player_timed_by_the_server(): void
    {
        $game = $this->engine->start($this->openRoom(), $this->host);
        $choices = $this->choicesOf($this->current($game));

        $this->travel(4)->seconds();
        $answer = $this->engine->submitAnswer($game, $this->guest, $choices['correct']);
        $this->assertGreaterThanOrEqual(4000, $answer->response_ms);
        $this->assertLessThan(5000, $answer->response_ms);

        $this->expectException(DomainActionException::class);
        $this->engine->submitAnswer($game, $this->guest, $choices['wrong']);
    }

    public function test_late_and_foreign_answers_are_rejected(): void
    {
        $game = $this->engine->start($this->openRoom(['seconds_per_question' => 10]), $this->host);
        $gq = $this->current($game);

        try {
            $this->engine->submitAnswer($game, $this->guest, 999999);
            $this->fail('Accepted a choice from another question.');
        } catch (DomainActionException) {
        }

        try {
            $this->engine->submitAnswer($game, $this->player(), $this->choicesOf($gq)['correct']);
            $this->fail('Accepted an answer from outside the room.');
        } catch (AuthorizationException) {
        }

        $this->travel(12)->seconds();
        $this->expectException(DomainActionException::class);
        $this->engine->submitAnswer($game, $this->guest, $this->choicesOf($gq)['correct']);
    }

    public function test_question_closes_when_everyone_answered_then_advances_after_the_reveal(): void
    {
        $game = $this->engine->start($this->openRoom(), $this->host);
        app(RoomService::class)->touch($this->room, $this->guest);
        $choices = $this->choicesOf($this->current($game));

        $this->engine->submitAnswer($game, $this->host, $choices['correct']);
        $this->assertNull($this->current($game)->closed_at);

        $this->engine->submitAnswer($game, $this->guest, $choices['wrong']);
        $first = $this->current($game);
        $this->assertNotNull($first->closed_at);

        $state = $this->engine->state($game, $this->host);
        $this->assertSame('reveal', $state['phase']);
        $this->assertSame($choices['correct'], $state['reveal']['correct_choice_id']);
        $this->assertTrue($state['reveal']['mine']['correct']);
        $this->assertGreaterThan(0, $state['reveal']['mine']['points']);
        $this->assertSame($this->host->id, $state['leaderboard'][0]['user_id']);

        $this->engine->tick($game);
        $this->assertSame(1, $game->fresh()->current_position);

        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $this->engine->tick($game);
        $this->assertSame(2, $game->fresh()->current_position);
        $this->assertNotNull($this->current($game)->opened_at);
    }

    public function test_deadline_closes_the_question_without_everyone_answering(): void
    {
        $game = $this->engine->start($this->openRoom(['seconds_per_question' => 10]), $this->host);

        $this->travel(11)->seconds();
        $this->engine->tick($game);

        $this->assertNotNull($this->current($game)->closed_at);
        $this->assertNull($this->engine->state($game, $this->guest)['reveal']['mine']);
    }

    public function test_duplicate_answer_rule_excludes_identical_answers_in_a_game(): void
    {
        $third = $this->player('Third');
        $room = $this->openRoom(['duplicate_rule' => true]);
        app(RoomService::class)->join($room, $third);
        $game = $this->engine->start($room, $this->host);
        $choices = $this->choicesOf($this->current($game));

        $this->engine->submitAnswer($game, $this->host, $choices['correct']);
        $this->engine->submitAnswer($game, $this->guest, $choices['correct']);
        $this->engine->submitAnswer($game, $third, $choices['wrong']);
        $this->travel(21)->seconds();
        $this->engine->tick($game);

        $answers = $this->current($game)->answers()->get()->keyBy('user_id');
        $this->assertTrue($answers[$this->host->id]->is_correct);
        $this->assertTrue($answers[$this->host->id]->is_duplicate_excluded);
        $this->assertSame(0, $answers[$this->host->id]->points);
        $this->assertSame(0, $answers[$this->guest->id]->points);
        $this->assertFalse($answers[$third->id]->is_duplicate_excluded);

        $state = $this->engine->state($game, $this->host);
        $this->assertTrue($state['reveal']['mine']['excluded']);
        $this->assertSame([$choices['correct']], $state['reveal']['excluded_choice_ids']);
    }

    public function test_a_full_game_ends_with_ranked_results_and_usage_stats(): void
    {
        $game = $this->engine->start($this->openRoom(['question_count' => 2]), $this->host);

        foreach ([1, 2] as $position) {
            $choices = $this->choicesOf($this->current($game));
            $this->engine->submitAnswer($game, $this->host, $choices['correct']);
            $this->engine->submitAnswer($game, $this->guest, $choices['wrong']); // closes: everyone answered
            $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
            $this->engine->tick($game); // next question, or the end
        }

        $game->refresh();
        $this->assertTrue($game->isCompleted());
        $this->assertSame(RoomStatus::Completed, $this->room->fresh()->status);

        $results = $game->results()->get();
        $this->assertSame([$this->host->id, $this->guest->id], $results->pluck('user_id')->all());
        $this->assertSame(2, $results[0]->correct_count);
        $this->assertSame(0, $results[1]->score);
        $this->assertSame(2, $results[1]->incorrect_count);

        $played = GameQuestion::query()->where('game_id', $game->id)->pluck('question_id');
        $this->assertSame(2, (int) Question::query()->whereIn('id', $played)->sum('times_correct'));

        $state = $this->engine->state($game, $this->guest);
        $this->assertSame('completed', $state['phase']);
        $this->assertSame(1, $state['results'][0]['rank']);
    }

    public function test_reconnecting_keeps_the_recorded_answer_and_remaining_time(): void
    {
        $game = $this->engine->start($this->openRoom(['seconds_per_question' => 20]), $this->host);
        $choices = $this->choicesOf($this->current($game));
        $this->engine->submitAnswer($game, $this->guest, $choices['wrong']);

        $this->travel(5)->seconds();
        $state = $this->engine->state($game->fresh(), $this->guest);

        $this->assertSame($choices['wrong'], $state['my_choice_id']);
        $this->assertLessThanOrEqual(15000, $state['remaining_ms']);
        $this->assertGreaterThan(14000, $state['remaining_ms']);
    }

    public function test_room_page_drives_the_game_through_livewire(): void
    {
        $room = $this->openRoom();

        Livewire::actingAs($this->host)->test('pages::rooms.show', ['room' => $room])->call('start')->assertSee(__('play.game.question_of', ['n' => 1, 'total' => 3]));

        $game = $room->fresh()->game;
        $choices = $this->choicesOf($this->current($game));

        Livewire::actingAs($this->guest)->test('pages::rooms.show', ['room' => $room])
            ->call('answer', $choices['correct'])
            ->assertSee(__('play.game.locked_in'))
            ->call('refresh');

        $this->assertSame(1, $this->current($game)->answers()->count());
    }
}

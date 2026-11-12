<?php

declare(strict_types=1);

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Models\User;

/**
 * A shared first place after the last question adds one more question, up to
 * config('fakkroun.gameplay.tie_break_questions') (off in phpunit.xml).
 */
class TieBreakerTest extends GameplayTestCase
{
    private User $host;

    private User $guest;

    private GameEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        config(['fakkroun.gameplay.tie_break_questions' => 2]);

        $this->engine = app(GameEngine::class);
        $this->host = $this->player('Host');
        $this->guest = $this->player('Guest');
    }

    private function start(int $questions = 1): Game
    {
        $room = $this->room($this->host, ['question_count' => $questions]);
        app(RoomService::class)->join($room, $this->guest);

        return $this->engine->start($room, $this->host);
    }

    private function current(Game $game): GameQuestion
    {
        return GameQuestion::query()->where('game_id', $game->id)->where('position', $game->fresh()->current_position)->firstOrFail();
    }

    /**
     * Both players answer the current question, then its reveal ends.
     */
    private function play(Game $game, bool $hostRight, bool $guestRight): void
    {
        $question = Question::withTrashed()->with('choices')->findOrFail($this->current($game)->question_id);
        $wrong = $question->choices->firstWhere('id', '!=', $question->correct_choice_id)->id;

        $this->engine->submitAnswer($game, $this->host, $hostRight ? $question->correct_choice_id : $wrong);
        $this->engine->submitAnswer($game, $this->guest, $guestRight ? $question->correct_choice_id : $wrong);
        $this->engine->tick($game); // everyone answered: the question closes
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $this->engine->tick($game); // next question, a tie-breaker, or the end
    }

    public function test_a_tie_for_first_place_adds_a_question_that_decides_the_winner(): void
    {
        $this->publishQuestions(4);
        $game = $this->start();

        $this->play($game, hostRight: false, guestRight: false);

        $game->refresh();
        $this->assertFalse($game->isCompleted());
        $this->assertSame(2, $game->total_questions);
        $this->assertSame(2, $game->current_position);

        $extra = $this->current($game);
        $this->assertTrue($extra->is_tie_breaker);
        $this->assertTrue($extra->isOpen());
        $this->assertSame(2, GameQuestion::query()->where('game_id', $game->id)->distinct()->count('question_id'));
        $this->assertTrue($this->engine->state($game, $this->host)['tie_breaker']);

        $this->play($game, hostRight: false, guestRight: true);

        $game->refresh();
        $this->assertTrue($game->isCompleted());
        $this->assertSame(2, $game->total_questions);
        $this->assertSame([$this->guest->id, $this->host->id], $game->results()->pluck('user_id')->all());
    }

    public function test_no_question_is_added_when_there_is_a_clear_winner(): void
    {
        $this->publishQuestions(4);
        $game = $this->start();

        $this->play($game, hostRight: true, guestRight: false);

        $game->refresh();
        $this->assertTrue($game->isCompleted());
        $this->assertSame(1, $game->total_questions);
        $this->assertSame(0, GameQuestion::query()->where('game_id', $game->id)->where('is_tie_breaker', true)->count());
        $this->assertFalse($this->engine->state($game, $this->host)['tie_breaker']);
    }

    public function test_tie_breakers_stop_at_the_configured_limit(): void
    {
        $this->publishQuestions(6);
        $game = $this->start();

        foreach ([1, 2, 3] as $ignored) {
            $this->play($game, hostRight: false, guestRight: false);
        }

        $game->refresh();
        $this->assertTrue($game->isCompleted());
        $this->assertSame(3, $game->total_questions); // 1 planned + 2 tie-breakers
        $this->assertSame(2, $game->results()->count());
    }

    public function test_a_tied_game_ends_when_no_new_question_is_left(): void
    {
        $this->publishQuestions(1);
        $game = $this->start();

        $this->play($game, hostRight: false, guestRight: false);

        $game->refresh();
        $this->assertTrue($game->isCompleted());
        $this->assertSame(1, $game->total_questions);
    }

    public function test_tie_breakers_can_be_turned_off(): void
    {
        config(['fakkroun.gameplay.tie_break_questions' => 0]);
        $this->publishQuestions(4);
        $game = $this->start();

        $this->play($game, hostRight: false, guestRight: false);

        $this->assertTrue($game->fresh()->isCompleted());
    }

    public function test_a_player_who_left_does_not_cause_a_tie_breaker(): void
    {
        $this->publishQuestions(4);
        $game = $this->start();

        // Host and guest both miss; the guest then leaves: nobody left to tie with.
        $question = Question::withTrashed()->with('choices')->findOrFail($this->current($game)->question_id);
        $wrong = $question->choices->firstWhere('id', '!=', $question->correct_choice_id)->id;
        $this->engine->submitAnswer($game, $this->host, $wrong);
        $this->engine->submitAnswer($game, $this->guest, $wrong);
        $this->engine->tick($game);
        app(RoomService::class)->leave($game->room, $this->guest);
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $this->engine->tick($game);

        $this->assertTrue($game->fresh()->isCompleted());
        $this->assertSame(1, $game->fresh()->total_questions);
    }
}

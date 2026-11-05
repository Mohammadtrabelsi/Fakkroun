<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\AchievementService;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\UserAchievement;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Models\User;

class ProgressTest extends GameplayTestCase
{
    /**
     * Plays a full game; $winnerAlwaysRight answers correctly, the other wrongly.
     */
    private function playGame(User $winner, User $other, int $questions = 5): Game
    {
        $this->publishQuestions($questions);
        $room = $this->room($winner, ['question_count' => $questions, 'name' => 'Room '.uniqid()]);
        app(RoomService::class)->join($room, $other);
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $winner);

        for ($i = 0; $i < $questions; $i++) {
            $game->refresh();
            $gq = $game->gameQuestions()->where('position', $game->current_position)->firstOrFail();
            $question = Question::find($gq->question_id);
            $engine->submitAnswer($game, $winner, $question->correct_choice_id);
            $engine->submitAnswer($game, $other, $question->choices()->where('id', '!=', $question->correct_choice_id)->value('id'));
            $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
            $engine->tick($game->fresh());
        }

        $this->assertTrue($game->fresh()->isCompleted());

        return $game->fresh();
    }

    public function test_finishing_a_game_awards_achievements_once(): void
    {
        $winner = $this->player('Amira');
        $other = $this->player('Youssef');
        $game = $this->playGame($winner, $other);

        $mine = app(AchievementService::class)->for($winner)->pluck('key')->all();
        $this->assertSame(['first_game', 'first_win', 'perfect_game', 'quick_thinker'], $mine);
        $this->assertSame(['first_game'], app(AchievementService::class)->for($other)->pluck('key')->all());
        $this->assertSame(4, $winner->notifications()->count());

        // Awarding again for the same game changes nothing and notifies nobody.
        app(AchievementService::class)->awardFor($game);
        $this->assertSame(4, UserAchievement::query()->where('user_id', $winner->id)->count());
        $this->assertSame(4, $winner->notifications()->count());
    }

    public function test_short_games_are_never_perfect(): void
    {
        $winner = $this->player();
        $this->playGame($winner, $this->player('Other'), 3);

        $this->assertNotContains('perfect_game', app(AchievementService::class)->for($winner)->pluck('key')->all());
    }

    public function test_three_wins_in_a_row_is_a_hat_trick(): void
    {
        $winner = $this->player('Amira');
        $other = $this->player('Youssef');

        foreach (range(1, 3) as $round) {
            $this->playGame($winner, $other, 1);
            $this->travel(1)->minutes();
        }

        $this->assertContains('hat_trick', app(AchievementService::class)->for($winner)->pluck('key')->all());
        $this->assertNotContains('hat_trick', app(AchievementService::class)->for($other)->pluck('key')->all());
    }

    public function test_history_lists_finished_games_with_a_review_link(): void
    {
        $winner = $this->player('Amira');
        $other = $this->player('Youssef');
        $game = $this->playGame($winner, $other, 1);

        $this->actingAs($winner)->get(route('rooms.history'))
            ->assertOk()
            ->assertSee($game->room->name)
            ->assertSee(route('rooms.results', $game->room), false)
            ->assertSee(__('progress.history.players', ['count' => 2]));

        $this->actingAs($this->player('New'))->get(route('rooms.history'))->assertOk()->assertSee(__('progress.history.empty'));
    }

    public function test_dashboard_and_profile_show_progress(): void
    {
        $winner = $this->player('Amira');
        $this->playGame($winner, $this->player('Youssef'), 1);

        $this->actingAs($winner)->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('progress.achievements.first_win.name'))
            ->assertSee(route('rooms.history'), false);

        $this->actingAs($this->player('Visitor'))->get(route('players.show', $winner))
            ->assertOk()
            ->assertSee(__('progress.achievements.first_win.name'));
    }

    public function test_answers_can_be_picked_with_the_keyboard(): void
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $this->publishQuestions(1);
        $room = $this->room($host, ['question_count' => 1]);
        app(RoomService::class)->join($room, $guest);
        app(GameEngine::class)->start($room, $host);

        $this->actingAs($host)->get(route('rooms.show', $room))
            ->assertOk()
            ->assertSee('data-answer', false)
            ->assertSee('aria-keyshortcuts="1"', false)
            ->assertSee(__('play.game2.keyboard_hint'));
    }
}

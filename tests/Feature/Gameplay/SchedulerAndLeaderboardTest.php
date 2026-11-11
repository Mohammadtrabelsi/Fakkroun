<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\LeaderboardService;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Notifications\QuestionReviewed;
use App\Services\PlatformSettings;

class SchedulerAndLeaderboardTest extends GameplayTestCase
{
    public function test_games_advance_without_anyone_polling(): void
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
        $this->publishQuestions(2);
        $room = $this->room($host, ['question_count' => 2, 'seconds_per_question' => 10]);
        app(RoomService::class)->join($room, $guest);
        $game = app(GameEngine::class)->start($room, $host);

        $this->travel(11)->seconds();
        $this->artisan('games:advance')->assertSuccessful();
        $this->assertNotNull($game->gameQuestions()->where('position', 1)->value('closed_at'));

        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $this->artisan('games:advance')->assertSuccessful();
        $this->assertSame(2, $game->fresh()->current_position);

        $this->travel(11)->seconds();
        $this->artisan('games:advance');
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $this->artisan('games:advance');
        $this->assertTrue($game->fresh()->isCompleted());
    }

    public function test_expired_lobbies_are_cancelled(): void
    {
        $room = $this->room($this->player());
        $this->travel((int) app(PlatformSettings::class)->get('game.room_expiry_minutes') + 1)->minutes();

        $this->artisan('rooms:expire')->assertSuccessful();

        $this->assertSame(RoomStatus::Cancelled, $room->fresh()->status);
    }

    public function test_leaderboard_survives_a_serialising_cache_store(): void
    {
        // Tests use the array store, which keeps objects as they are. Real stores serialise,
        // and objects are refused on the way back (cache.serializable_classes = false).
        config(['cache.default' => 'file', 'cache.stores.file.path' => sys_get_temp_dir().'/fakkroun-cache-'.uniqid()]);

        $leaderboard = app(LeaderboardService::class);
        $leaderboard->top('all');
        $cached = $leaderboard->top('all');

        $this->assertCount(0, $cached);
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk();
        $this->get(route('leaderboard', ['period' => 'all']))->assertOk();
    }

    public function test_leaderboard_and_profile_reflect_finished_games(): void
    {
        $host = $this->player('Amira');
        $guest = $this->player('Youssef');
        $this->publishQuestions(1);
        $room = $this->room($host, ['question_count' => 1]);
        app(RoomService::class)->join($room, $guest);
        $engine = app(GameEngine::class);
        $game = $engine->start($room, $host);
        $question = Question::find($game->gameQuestions()->first()->question_id);
        $engine->submitAnswer($game, $host, $question->correct_choice_id);
        $engine->submitAnswer($game, $guest, $question->choices()->where('id', '!=', $question->correct_choice_id)->value('id'));
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $engine->tick($game);

        $top = app(LeaderboardService::class)->top('all');
        $this->assertSame('Amira', $top->first()->name);
        $this->assertSame(1, $top->first()->wins);

        $this->get(route('leaderboard', ['period' => 'all']))->assertOk()->assertSee('Amira');
        $this->actingAs($guest)->get(route('players.show', $host))->assertOk()->assertSee('Amira');
        $this->assertSame(1, app(LeaderboardService::class)->stats($host)['wins']);
        $this->assertSame(100.0, app(LeaderboardService::class)->stats($host)['correct_rate']);
    }

    public function test_players_have_an_inbox(): void
    {
        $player = $this->player();
        $question = Question::factory()->create(['creator_id' => $player->id, 'source' => 'community', 'category_id' => $this->category->id]);
        $player->notify(new QuestionReviewed($question, 'approved'));

        $this->actingAs($player)->get(route('notifications'))->assertOk()->assertSee(__('questions.notifications.approved'));
        $this->actingAs($player)->get(route('dashboard'))->assertSee(route('notifications'), false);
    }

    public function test_missing_pages_show_the_mascot(): void
    {
        $this->get('/no-such-page')->assertNotFound()->assertSee(__('play.not_found.title'))->assertSee('cx="123" cy="54" r="5"', false);
    }
}

<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\PlatformAnalytics;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Models\User;
use Tests\Feature\Gameplay\GameplayTestCase;

class PlatformAnalyticsTest extends GameplayTestCase
{
    public function test_an_empty_platform_shows_zeros_not_invented_figures(): void
    {
        $analytics = app(PlatformAnalytics::class);
        ['from' => $from, 'to' => $to] = $analytics->period(null, null);
        $overview = $analytics->overview($from, $to);

        $this->assertSame(0, $overview['games_completed']);
        $this->assertSame(0, $overview['answers']);
        $this->assertNull($overview['correct_rate']);
        $this->assertCount(30, $analytics->dailyGames($from, $to));
    }

    public function test_figures_come_from_recorded_games(): void
    {
        $host = $this->player('Host');
        $guest = $this->player('Guest');
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

        $analytics = app(PlatformAnalytics::class);
        ['from' => $from, 'to' => $to] = $analytics->period(null, null);
        $overview = $analytics->overview($from, $to);

        $this->assertSame(1, $overview['games_completed']);
        $this->assertSame(2, $overview['active_players']);
        $this->assertSame(2, $overview['answers']);
        $this->assertSame(50.0, $overview['correct_rate']);
        $this->assertSame(1, array_sum($analytics->dailyGames($from, $to)));
        $this->assertSame(2, $analytics->breakdown($from, $to)['categories'][0]['answers']);
        $this->assertSame([], $analytics->questions($from, $to)['hardest'], 'Below the minimum answer count, questions are not ranked.');
        $this->assertSame(2, $analytics->questions($from, $to)['most_played'][0]['answers']);
    }

    public function test_the_analytics_screen_is_for_back_office_users(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));

        $this->actingAs($admin)->get(route('admin.analytics'))->assertOk()->assertSee(__('analytics.title'));
        $this->actingAs($this->player())->get(route('admin.analytics'))->assertForbidden();
    }
}

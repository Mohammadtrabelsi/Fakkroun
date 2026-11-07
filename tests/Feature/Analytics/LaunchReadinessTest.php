<?php

namespace Tests\Feature\Analytics;

use App\Domain\Analytics\DailyRollup;
use App\Domain\Analytics\PlatformAnalytics;
use App\Domain\Gameplay\GameEngine;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\JobFailedAlert;
use App\Services\PlatformSettings;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\Feature\Gameplay\GameplayTestCase;

/** Next step 10: fair play, daily rollups, calibration, moderation health, job alerts. */
class LaunchReadinessTest extends GameplayTestCase
{
    private function playOne(): Game
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
        $this->travel(2)->seconds();
        $engine->submitAnswer($game, $guest, $question->correct_choice_id);
        $this->travel((int) config('fakkroun.gameplay.reveal_seconds') + 1)->seconds();
        $engine->tick($game->fresh());

        return $game->fresh();
    }

    public function test_impossibly_fast_answers_are_flagged_but_scored_as_usual(): void
    {
        $game = $this->playOne();
        $answers = PlayerAnswer::query()->orderBy('id')->get();

        $this->assertTrue($answers[0]->flagged_fast, 'Answered in 0 ms.');
        $this->assertFalse($answers[1]->flagged_fast, 'Answered after 2 s.');
        $this->assertGreaterThan(0, $answers[0]->points, 'A flag never changes the score.');

        app(PlatformSettings::class)->update(['quality' => ['fast_answer_ms' => 0]]);
        $this->playOne();
        $this->assertSame(1, PlayerAnswer::query()->where('flagged_fast', true)->count(), 'Setting 0 switches flagging off.');
    }

    public function test_players_above_the_threshold_are_listed_for_review(): void
    {
        app(PlatformSettings::class)->update(['quality' => ['fast_answer_review_threshold' => 1]]);
        $this->playOne();

        $analytics = app(PlatformAnalytics::class);
        ['from' => $from, 'to' => $to] = $analytics->period(null, null);
        $fair = $analytics->fairPlay($from, $to);

        $this->assertSame(1, $fair['flagged']);
        $this->assertSame('Host', $fair['players'][0]['name']);
    }

    public function test_daily_rollups_are_idempotent_skip_today_and_feed_the_chart(): void
    {
        $this->travelTo(Carbon::yesterday()->setTime(10, 0));
        $this->playOne();
        $this->travelBack();

        $this->artisan('analytics:rollup')->assertSuccessful();
        $this->artisan('analytics:rollup')->assertSuccessful();

        $row = DB::table('platform_daily_stats')->sole();
        $this->assertSame(Carbon::yesterday()->toDateString(), Carbon::parse($row->day)->toDateString());
        $this->assertSame(1, (int) $row->games_started);
        $this->assertSame(1, (int) $row->games_completed);
        $this->assertSame(2, (int) $row->answers);
        $this->assertSame(2, (int) $row->active_players);
        $this->assertFalse(app(DailyRollup::class)->store(Carbon::today()), 'Today is not over: never stored.');

        DB::table('platform_daily_stats')->update(['games_started' => 7]);
        $days = app(PlatformAnalytics::class)->dailyGames(Carbon::yesterday()->startOfDay(), Carbon::today()->endOfDay());
        $this->assertSame(7, $days[Carbon::yesterday()->toDateString()], 'Past days are read from the rollup.');
    }

    public function test_questions_contradicting_their_difficulty_are_listed(): void
    {
        $easy = Question::factory()->published()->create(['category_id' => $this->category->id, 'difficulty' => 'easy']);
        $hard = Question::factory()->published()->create(['category_id' => $this->category->id, 'difficulty' => 'hard']);
        $fine = Question::factory()->published()->create(['category_id' => $this->category->id, 'difficulty' => 'medium']);
        Question::query()->whereKey($easy->id)->update(['times_played' => 40, 'times_correct' => 4]);
        Question::query()->whereKey($hard->id)->update(['times_played' => 40, 'times_correct' => 38]);
        Question::query()->whereKey($fine->id)->update(['times_played' => 40, 'times_correct' => 4]);

        $uuids = collect(app(PlatformAnalytics::class)->calibration())->pluck('uuid')->all();

        $this->assertContains($easy->uuid, $uuids);
        $this->assertContains($hard->uuid, $uuids);
        $this->assertNotContains($fine->uuid, $uuids);
    }

    public function test_moderation_health(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        $waiting = Question::factory()->create(['category_id' => $this->category->id, 'status' => 'pending', 'submitted_at' => now()->subHours(30)]);
        $other = Question::factory()->create(['category_id' => $this->category->id, 'status' => 'pending']);
        app(ModerationService::class)->approve($other, $admin);

        $health = app(PlatformAnalytics::class)->moderation();

        $this->assertSame(1, $health['waiting']);
        $this->assertGreaterThanOrEqual(30, $health['oldest_hours']);
        $this->assertSame(1, array_sum($health['weekly']));
        $this->assertNotNull($waiting);
    }

    public function test_a_failed_job_alerts_admins_once_per_ten_minutes(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        $job = Mockery::mock(Job::class);
        $job->shouldReceive('resolveName')->andReturn('App\\Domain\\Ai\\Jobs\\GenerateQuestionBatch');

        event(new JobFailed('database', $job, new \RuntimeException('Provider down')));
        event(new JobFailed('database', $job, new \RuntimeException('Provider down')));

        $this->assertSame(1, $admin->notifications()->where('type', JobFailedAlert::class)->count());
        $this->assertSame(0, $this->player()->notifications()->count(), 'Players are not alerted.');
    }

    public function test_the_analytics_page_shows_the_new_sections(): void
    {
        $admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));

        $this->actingAs($admin)->get(route('admin.analytics'))
            ->assertOk()
            ->assertSee(__('analytics.fair_play.title'))
            ->assertSee(__('analytics.calibration.title'))
            ->assertSee(__('analytics.moderation.title'));
    }
}

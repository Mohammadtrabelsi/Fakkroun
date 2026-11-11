<?php

namespace Database\Seeders;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\GameQuestionService;
use App\Domain\Gameplay\GameResultService;
use App\Domain\Gameplay\GameService;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\PlayerAnswerService;
use App\Domain\Gameplay\RoomCodeGenerator;
use App\Domain\Gameplay\RoomPlayerService;
use App\Domain\Gameplay\RoomService;
use App\Domain\Gameplay\ScoringEngine;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionPoolEntryService;
use App\Domain\Questions\QuestionService;
use App\Models\User;
use App\Services\PlatformSettings;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * One finished demo game (room, game_questions, player answers and results)
 * built from the published question pool, so game history, recaps and the
 * leaderboard have something to show. Demo tier only (DatabaseSeeder::DEMO);
 * runs after FakkrounPlayableQuestionSeeder and after the demo users exist.
 *
 * Idempotent: skipped when the demo room already exists.
 */
class FakkrounDemoGameSeeder extends Seeder
{
    public const ROOM_NAME = 'Demo game: Sfax coast';

    private const LANGUAGE = 'en';

    private const QUESTION_COUNT = 8;

    private const SECONDS_PER_QUESTION = 20;

    public function run(): void
    {
        if (app(RoomService::class)->query()->where('name', self::ROOM_NAME)->exists()) {
            return;
        }

        $host = app(UserService::class)->query()->role('admin')->first();
        $players = app(UserService::class)->query()->whereKeyNot($host?->id)->orderBy('id')->limit(3)->get();

        if ($host === null || $players->isEmpty()) {
            $this->command?->warn('Not enough users: skipping the demo game.');

            return;
        }

        $questionIds = app(QuestionPoolEntryService::class)->query()
            ->where('language', self::LANGUAGE)
            ->where('is_sponsored', false)
            ->orderBy('question_id')
            ->limit(self::QUESTION_COUNT)
            ->pluck('question_id');

        if ($questionIds->isEmpty()) {
            $this->command?->warn('No published questions: skipping the demo game.');

            return;
        }

        /** @var Collection<int, Question> $questions */
        $questions = app(QuestionService::class)->query()->with('choices')->whereIn('id', $questionIds)->get()->keyBy('id');
        $everyone = collect([$host])->merge($players)->values();
        $scoring = app(PlatformSettings::class)->all()['scoring'];
        $startedAt = Carbon::now()->subDay()->setTime(18, 0);
        $durationMs = self::SECONDS_PER_QUESTION * 1000;

        // Deterministic "random" answers so every seed produces the same game.
        mt_srand(2026);

        DB::transaction(function () use ($host, $everyone, $questions, $questionIds, $scoring, $startedAt, $durationMs): void {
            $settings = [
                'category_ids' => $questions->pluck('category_id')->unique()->values()->all(),
                'question_count' => $questionIds->count(),
                'seconds_per_question' => self::SECONDS_PER_QUESTION,
                'difficulty' => 'any',
                'language' => self::LANGUAGE,
                'duplicate_rule' => false,
                'duplicate_threshold' => (int) $scoring['duplicate_rule_threshold'],
            ];

            $room = app(RoomService::class)->query()->create([
                'host_id' => $host->id,
                'name' => self::ROOM_NAME,
                'visibility' => 'private',
                'code' => app(RoomCodeGenerator::class)->generate(),
                'max_players' => 8,
                'expires_at' => $startedAt->copy()->addHours(2),
                'settings' => $settings,
            ]);
            $room->status = RoomStatus::Completed;
            $room->save();

            foreach ($everyone as $player) {
                app(RoomPlayerService::class)->query()->create([
                    'room_id' => $room->id,
                    'user_id' => $player->id,
                    'joined_at' => $startedAt->copy()->subMinutes(5),
                    'last_seen_at' => $startedAt,
                ]);
            }

            $gameSettings = $settings + [
                'points_easy' => $scoring['points_easy'],
                'points_medium' => $scoring['points_medium'],
                'points_hard' => $scoring['points_hard'],
                'speed_bonus_max' => $scoring['speed_bonus_max'],
                'reveal_seconds' => (int) config('fakkroun.gameplay.reveal_seconds', 6),
            ];

            $game = app(GameService::class)->query()->create([
                'room_id' => $room->id,
                'status' => Game::COMPLETED,
                'settings' => $gameSettings,
                'total_questions' => $questionIds->count(),
                'current_position' => $questionIds->count(),
                'started_at' => $startedAt,
            ]);

            $totals = $everyone->mapWithKeys(fn (User $u) => [$u->id => [
                'score' => 0, 'correct' => 0, 'incorrect' => 0, 'unanswered' => 0, 'ms' => 0,
            ]])->all();
            $openedAt = $startedAt->copy();

            foreach ($questionIds->values() as $index => $questionId) {
                $question = $questions[$questionId];
                $deadline = $openedAt->copy()->addSeconds(self::SECONDS_PER_QUESTION);

                $gameQuestion = app(GameQuestionService::class)->query()->create([
                    'game_id' => $game->id,
                    'question_id' => $question->id,
                    'position' => $index + 1,
                    'question_version' => $question->version,
                    'language' => self::LANGUAGE,
                    'opened_at' => $openedAt,
                    'deadline_at' => $deadline,
                    'closed_at' => $deadline,
                ]);

                $answered = 0;
                $correctCount = 0;

                foreach ($everyone as $seat => $player) {
                    // Roughly one unanswered question in ten.
                    if (mt_rand(1, 10) === 1) {
                        $totals[$player->id]['unanswered']++;

                        continue;
                    }

                    // Stronger players further up the table: 80%, 70%, 60%, 50%.
                    $isCorrect = mt_rand(1, 100) <= 80 - $seat * 10;
                    $choiceId = $isCorrect
                        ? $question->correct_choice_id
                        : $question->choices->where('id', '!=', $question->correct_choice_id)->values()[mt_rand(0, 2)]->id;
                    $responseMs = mt_rand(1500, $durationMs - 500);
                    $points = ScoringEngine::points($isCorrect, false, $question->difficulty->value, $responseMs, $durationMs, $gameSettings);

                    app(PlayerAnswerService::class)->query()->create([
                        'game_question_id' => $gameQuestion->id,
                        'user_id' => $player->id,
                        'choice_id' => $choiceId,
                        'received_at' => $openedAt->copy()->addMilliseconds($responseMs),
                        'response_ms' => $responseMs,
                        'is_correct' => $isCorrect,
                        'is_duplicate_excluded' => false,
                        'points' => $points,
                    ]);

                    $answered++;
                    $correctCount += $isCorrect ? 1 : 0;
                    $totals[$player->id]['score'] += $points;
                    $totals[$player->id][$isCorrect ? 'correct' : 'incorrect']++;
                    $totals[$player->id]['ms'] += $responseMs;
                }

                app(QuestionService::class)->query()->whereKey($question->id)->update([
                    'times_played' => DB::raw('times_played + '.$answered),
                    'times_correct' => DB::raw('times_correct + '.$correctCount),
                ]);

                $openedAt = $deadline->copy()->addSeconds((int) $gameSettings['reveal_seconds']);
            }

            $ranked = collect($totals)
                ->sort(fn (array $a, array $b) => [$b['score'], $a['ms']] <=> [$a['score'], $b['ms']]);

            $rank = 1;

            foreach ($ranked as $userId => $row) {
                app(GameResultService::class)->query()->create([
                    'game_id' => $game->id,
                    'user_id' => $userId,
                    'score' => $row['score'],
                    'rank' => $rank++,
                    'correct_count' => $row['correct'],
                    'incorrect_count' => $row['incorrect'],
                    'excluded_count' => 0,
                    'unanswered_count' => $row['unanswered'],
                    'total_response_ms' => $row['ms'],
                ]);
            }

            $game->ended_at = $openedAt;
            $game->save();
        });

        $this->command?->info('Demo game seeded with '.$questionIds->count().' questions.');
    }
}

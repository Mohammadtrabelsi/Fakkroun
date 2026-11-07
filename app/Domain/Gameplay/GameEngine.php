<?php

namespace App\Domain\Gameplay;

use App\Domain\Advertising\SponsoredQuestionService;
use App\Domain\Gameplay\Enums\RoomStatus;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\GameResult;
use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\Models\RoomPlayer;
use App\Domain\Questions\Enums\TranslationStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Server-authoritative gameplay (spec §13–§15).
 *
 * The server owns the clock: each question has an opened_at and a
 * deadline_at, answers are timed on arrival, and correct answers are never
 * sent before a question closes. Progress is driven by tick(), which is
 * idempotent and safe to call from every client's poll (and later from a
 * scheduled job or a broadcast loop): it closes the current question once
 * its deadline passed or every active player answered, shows results for
 * fakkroun.gameplay.reveal_seconds, then opens the next question or ends
 * the game.
 */
class GameEngine
{
    public function __construct(
        private readonly QuestionSelector $selector,
        private readonly RoomService $rooms,
        private readonly PlatformSettings $settings,
    ) {}

    public function start(Room $room, User $host): Game
    {
        try {
            return DB::transaction(function () use ($room, $host): Game {
                /** @var Room $locked */
                $locked = Room::query()->whereKey($room->id)->lockForUpdate()->firstOrFail();

                if ($locked->host_id !== $host->id) {
                    throw new AuthorizationException(__('play.errors.host_only'));
                }

                if ($locked->status !== RoomStatus::Waiting) {
                    throw new DomainActionException(__('play.errors.already_started'));
                }

                $playerIds = RoomPlayer::query()->where('room_id', $locked->id)->whereNull('left_at')->pluck('user_id')->map(fn ($id) => (int) $id)->all();
                $minimum = (int) $this->settings->get('game.min_players');

                if (count($playerIds) < $minimum) {
                    throw new DomainActionException(__('play.errors.not_enough_players', ['min' => $minimum]));
                }

                $settings = $locked->settings;
                $difficulty = $settings['difficulty'] === 'any' ? null : $settings['difficulty'];
                $count = (int) $settings['question_count'];
                // No sponsored content in a room hosted by a paid (ad-free) account.
                $ads = $this->settings->all()['advertising'];
                $sponsorable = ($settings['include_sponsored'] ?? true) && $ads['enabled'] && ! $host->hasPaidPlan();

                // Sponsored round (story 16 S12): a block of consecutive questions from one campaign.
                $round = $sponsorable && $ads['sponsored_rounds_enabled'] && $count >= (int) $ads['sponsored_round_min_game_questions']
                    ? app(\App\Domain\Advertising\SponsoredRoundService::class)->pick($settings['language'], $settings['category_ids'], $difficulty, (int) $ads['sponsored_round_size'])
                    : null;
                $roundIds = $round['question_ids'] ?? [];

                $sponsored = $sponsorable
                    ? array_values(array_diff(app(SponsoredQuestionService::class)->pick($settings['language'], $settings['category_ids'], $difficulty, min($count - 1 - count($roundIds), (int) $ads['max_sponsored_per_game']) + count($roundIds)), $roundIds))
                    : [];
                $sponsored = array_slice($sponsored, 0, max(0, min($count - 1 - count($roundIds), (int) $ads['max_sponsored_per_game'])));

                $questionIds = [...$this->selector->select($settings['language'], $settings['category_ids'], $difficulty, $count - count($sponsored) - count($roundIds), $playerIds), ...$sponsored];
                shuffle($questionIds);

                // The round goes in one piece, never as the very first question.
                if ($roundIds !== []) {
                    array_splice($questionIds, random_int(min(1, count($questionIds)), count($questionIds)), 0, $roundIds);
                }

                if ($questionIds === []) {
                    throw new DomainActionException(__('play.errors.no_questions'));
                }

                $scoring = $this->settings->all()['scoring'];
                $game = Game::query()->create([
                    'room_id' => $locked->id,
                    'status' => Game::IN_PROGRESS,
                    'settings' => $settings + [
                        'points_easy' => $scoring['points_easy'],
                        'points_medium' => $scoring['points_medium'],
                        'points_hard' => $scoring['points_hard'],
                        'speed_bonus_max' => $scoring['speed_bonus_max'],
                        'reveal_seconds' => (int) config('fakkroun.gameplay.reveal_seconds', 6),
                    ],
                    'total_questions' => count($questionIds),
                    'current_position' => 1,
                    'started_at' => now(),
                ]);

                $versions = Question::query()->whereIn('id', $questionIds)->pluck('version', 'id');

                foreach (array_values($questionIds) as $index => $questionId) {
                    GameQuestion::query()->create([
                        'game_id' => $game->id,
                        'question_id' => $questionId,
                        'position' => $index + 1,
                        'question_version' => (int) ($versions[$questionId] ?? 1),
                        'language' => $settings['language'],
                        'sponsored_round_campaign_id' => in_array($questionId, $roundIds, true) ? $round['campaign_id'] : null,
                    ]);
                }

                $this->open($game, 1);

                $locked->status = RoomStatus::InProgress;
                $locked->save();

                return $game;
            });
        } catch (UniqueConstraintViolationException) {
            // Two simultaneous starts: the games.room_id index let one through.
            throw new DomainActionException(__('play.errors.already_started'));
        }
    }

    /**
     * Advances the game if its timing says so. Returns the current game.
     */
    public function tick(Game $game): Game
    {
        return DB::transaction(function () use ($game): Game {
            /** @var Game $locked */
            $locked = Game::query()->whereKey($game->id)->lockForUpdate()->firstOrFail();

            if ($locked->isCompleted()) {
                return $locked;
            }

            $current = $this->current($locked);
            $now = now();

            if ($current->isOpen()) {
                $deadlinePassed = $now->greaterThan($current->deadline_at->copy()->addMilliseconds((int) config('fakkroun.gameplay.answer_grace_ms', 750)));

                if ($deadlinePassed || $this->everyoneAnswered($locked, $current)) {
                    $this->close($locked, $current);
                }

                return $locked;
            }

            $revealEnds = $current->closed_at?->copy()->addSeconds((int) $locked->settings['reveal_seconds']);

            if ($revealEnds !== null && $now->greaterThanOrEqualTo($revealEnds)) {
                if ($locked->current_position < $locked->total_questions) {
                    $locked->current_position++;
                    $locked->save();
                    $this->open($locked, $locked->current_position);
                } else {
                    $this->complete($locked);
                }
            }

            return $locked;
        });
    }

    /**
     * Records one answer. The time is measured here, never taken from the client.
     */
    public function submitAnswer(Game $game, User $player, int $choiceId): PlayerAnswer
    {
        $answer = DB::transaction(function () use ($game, $player, $choiceId): PlayerAnswer {
            if (! $this->rooms->isMember($game->room, $player)) {
                throw new AuthorizationException(__('play.errors.not_in_room'));
            }

            /** @var GameQuestion|null $current */
            $current = GameQuestion::query()
                ->where('game_id', $game->id)
                ->where('position', Game::query()->whereKey($game->id)->value('current_position'))
                ->lockForUpdate()
                ->first();

            $now = now();
            $grace = (int) config('fakkroun.gameplay.answer_grace_ms', 750);

            if ($current === null || ! $current->isOpen() || $now->greaterThan($current->deadline_at->copy()->addMilliseconds($grace))) {
                throw new DomainActionException(__('play.errors.question_closed'));
            }

            $belongs = $current->question->choices()->whereKey($choiceId)->exists();

            if (! $belongs) {
                throw new DomainActionException(__('play.errors.invalid_choice'));
            }

            if (PlayerAnswer::query()->where('game_question_id', $current->id)->where('user_id', $player->id)->exists()) {
                throw new DomainActionException(__('play.errors.already_answered'));
            }

            $duration = max(1, (int) $current->opened_at->diffInMilliseconds($current->deadline_at));
            $responseMs = min($duration, max(0, (int) $current->opened_at->diffInMilliseconds($now)));
            $fastMs = (int) $this->settings->get('quality.fast_answer_ms');

            try {
                return PlayerAnswer::query()->create([
                    'game_question_id' => $current->id,
                    'user_id' => $player->id,
                    'choice_id' => $choiceId,
                    'received_at' => $now,
                    'response_ms' => $responseMs,
                    // Faster than a person can read the question: flagged for review, scored as usual.
                    'flagged_fast' => $fastMs > 0 && $responseMs < $fastMs,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw new DomainActionException(__('play.errors.already_answered'));
            }
        });

        // The last expected answer closes the question at once.
        $this->tick($game);

        return $answer;
    }

    /**
     * Everything one player's screen needs, from the server's point of view.
     * The correct answer appears only once the question is closed.
     *
     * @return array<string, mixed>
     */
    public function state(Game $game, User $viewer): array
    {
        $game->refresh();
        $current = $this->current($game);
        $question = $current->question()->with('choices')->first();
        $translation = $this->translationFor($question, $current->language);
        $answers = $current->answers()->get();
        $mine = $answers->firstWhere('user_id', $viewer->id);
        $now = now();

        $question->loadMissing(['category', 'author:id,name']);

        $state = [
            'status' => $game->status,
            'phase' => $game->isCompleted() ? 'completed' : ($current->isOpen() ? 'question' : 'reveal'),
            'position' => $current->position,
            'total' => $game->total_questions,
            'server_now' => $now->getTimestampMs(),
            'deadline_ms' => $current->deadline_at?->getTimestampMs(),
            'remaining_ms' => $current->isOpen() ? max(0, (int) $now->diffInMilliseconds($current->deadline_at, false)) : 0,
            'duration_ms' => $current->opened_at && $current->deadline_at ? (int) $current->opened_at->diffInMilliseconds($current->deadline_at) : 0,
            'category' => $question->category?->name,
            'author' => $question->author?->name,
            'source' => $question->source->value,
            'sponsor' => $question->is_sponsored && $current->sponsored_round_campaign_id === null ? \App\Domain\Advertising\Models\Campaign::query()->with('advertiser:id,name')->find($question->campaign_id)?->advertiser?->name : null,
            // Sponsored round: who presents it, and whether this is its first question (intro).
            'round_sponsor' => app(\App\Domain\Advertising\SponsoredRoundService::class)->sponsorName($current->sponsored_round_campaign_id),
            'round_intro' => $current->sponsored_round_campaign_id !== null && app(\App\Domain\Advertising\SponsoredRoundService::class)->isRoundStart($current),
            // Who has answered the current question — never which answer.
            'answered_user_ids' => $answers->pluck('user_id')->map(fn ($id) => (int) $id)->values()->all(),
            'duplicate_rule' => (bool) ($game->settings['duplicate_rule'] ?? false),
            'question' => [
                'body' => $translation?->body,
                'difficulty' => $question->difficulty->value,
                'choices' => $question->choices->map(fn ($choice) => [
                    'id' => $choice->id,
                    'letter' => $choice->letter($current->language),
                    'text' => $translation?->choiceText($choice->id),
                ])->all(),
            ],
            'my_choice_id' => $mine?->choice_id,
            'answered_count' => $answers->count(),
            'player_count' => RoomPlayer::query()->where('room_id', $game->room_id)->whereNull('left_at')->count(),
            'leaderboard' => $this->leaderboard($game),
            'reveal' => null,
            'results' => null,
        ];

        if (! $current->isOpen() && $current->closed_at !== null) {
            $state['reveal'] = [
                'correct_choice_id' => $question->correct_choice_id,
                'explanation' => $translation?->explanation,
                'reference_url' => $question->reference_url,
                'distribution' => $answers->countBy('choice_id')->all(),
                'excluded_choice_ids' => $answers->where('is_duplicate_excluded', true)->pluck('choice_id')->unique()->values()->all(),
                'mine' => $mine ? [
                    'correct' => $mine->is_correct,
                    'excluded' => $mine->is_duplicate_excluded,
                    'points' => $mine->points,
                    'response_ms' => $mine->response_ms,
                ] : null,
                'next_in_ms' => max(0, (int) $now->diffInMilliseconds($current->closed_at->copy()->addSeconds((int) $game->settings['reveal_seconds']), false)),
            ];
        }

        if ($game->isCompleted()) {
            $state['results'] = $game->results()->with('user:id,name,avatar_path')->get()->map(fn (GameResult $result) => [
                'user_id' => $result->user_id,
                'name' => $result->user?->name,
                'rank' => $result->rank,
                'score' => $result->score,
                'correct' => $result->correct_count,
                'incorrect' => $result->incorrect_count,
                'excluded' => $result->excluded_count,
                'unanswered' => $result->unanswered_count,
                'total_response_ms' => $result->total_response_ms,
            ])->all();
        }

        return $state;
    }

    /**
     * "What we learned": every question of a finished game with its correct
     * answer and the viewer's result. Only available once the game is over.
     *
     * @return array<int, array<string, mixed>>
     */
    public function recap(Game $game, User $viewer): array
    {
        if (! $game->fresh()->isCompleted()) {
            return [];
        }

        return GameQuestion::query()
            ->where('game_id', $game->id)
            ->orderBy('position')
            ->with(['question.choices', 'answers' => fn ($query) => $query->where('user_id', $viewer->id)])
            ->get()
            ->map(function (GameQuestion $gq): array {
                $question = $gq->question;
                $translation = $this->translationFor($question, $gq->language);
                $mine = $gq->answers->first();

                return [
                    'position' => $gq->position,
                    'body' => $translation?->body,
                    'correct_text' => $translation?->choiceText((int) $question->correct_choice_id),
                    'explanation' => $translation?->explanation,
                    'reference_url' => $question->reference_url,
                    'answered' => $mine !== null,
                    'correct' => (bool) $mine?->is_correct,
                    'excluded' => (bool) $mine?->is_duplicate_excluded,
                    'points' => (int) ($mine?->points ?? 0),
                ];
            })
            ->all();
    }

    private function current(Game $game): GameQuestion
    {
        return GameQuestion::query()->where('game_id', $game->id)->where('position', $game->current_position)->firstOrFail();
    }

    private function open(Game $game, int $position): void
    {
        $now = now();

        $opened = GameQuestion::query()->where('game_id', $game->id)->where('position', $position)->whereNull('opened_at')->update([
            'opened_at' => $now,
            'deadline_at' => $now->copy()->addSeconds((int) $game->settings['seconds_per_question']),
        ]);

        if ($opened > 0) {
            // A sponsored question, or a sponsored round (at its first question), is billed once per room.
            $gq = GameQuestion::query()->where('game_id', $game->id)->where('position', $position)->first();

            if ($gq->sponsored_round_campaign_id !== null) {
                app(\App\Domain\Advertising\SponsoredRoundService::class)->recordDelivery($gq);
            } else {
                app(SponsoredQuestionService::class)->recordDelivery($gq);
            }
        }
    }

    /**
     * Players count as present when they have not left and were seen within
     * the reconnection grace period (spec §13.4).
     */
    private function everyoneAnswered(Game $game, GameQuestion $current): bool
    {
        $grace = (int) $this->settings->get('game.reconnect_grace_seconds');

        $present = RoomPlayer::query()
            ->where('room_id', $game->room_id)
            ->whereNull('left_at')
            ->where('last_seen_at', '>=', now()->subSeconds(max(5, $grace)))
            ->pluck('user_id');

        if ($present->isEmpty()) {
            return false;
        }

        return PlayerAnswer::query()->where('game_question_id', $current->id)->whereIn('user_id', $present)->count() >= $present->count();
    }

    private function close(Game $game, GameQuestion $current): void
    {
        $question = $current->question;
        $answers = $current->answers()->get();
        $settings = $game->settings;
        $duration = max(1, (int) $current->opened_at->diffInMilliseconds($current->deadline_at));

        $excluded = ($settings['duplicate_rule'] ?? false)
            ? DuplicateAnswerRule::excludedChoices($answers->pluck('choice_id', 'user_id')->all(), (int) ($settings['duplicate_threshold'] ?? 2))
            : [];

        $correctCount = 0;

        foreach ($answers as $answer) {
            $isCorrect = $answer->choice_id === $question->correct_choice_id;
            $isExcluded = in_array($answer->choice_id, $excluded, true);
            $correctCount += $isCorrect ? 1 : 0;

            $answer->forceFill([
                'is_correct' => $isCorrect,
                'is_duplicate_excluded' => $isExcluded,
                'points' => ScoringEngine::points($isCorrect, $isExcluded, $question->difficulty->value, $answer->response_ms, $duration, $settings),
            ])->save();
        }

        $current->closed_at = now();
        $current->save();

        Question::withTrashed()->whereKey($question->id)->update([
            'times_played' => DB::raw('times_played + '.$answers->count()),
            'times_correct' => DB::raw('times_correct + '.$correctCount),
        ]);
    }

    private function complete(Game $game): void
    {
        $gameQuestionIds = GameQuestion::query()->where('game_id', $game->id)->pluck('id');
        $answers = PlayerAnswer::query()->whereIn('game_question_id', $gameQuestionIds)->get()->groupBy('user_id');
        $playerIds = RoomPlayer::query()->where('room_id', $game->room_id)->where('joined_at', '<=', $game->started_at)->pluck('user_id')
            ->merge($answers->keys())->map(fn ($id) => (int) $id)->unique();

        $rows = $playerIds->map(function (int $userId) use ($answers, $game): array {
            $mine = $answers->get($userId, collect());

            return [
                'game_id' => $game->id,
                'user_id' => $userId,
                'score' => (int) $mine->sum('points'),
                'correct_count' => $mine->where('is_correct', true)->count(),
                'incorrect_count' => $mine->where('is_correct', false)->count(),
                'excluded_count' => $mine->where('is_duplicate_excluded', true)->count(),
                'unanswered_count' => max(0, $game->total_questions - $mine->count()),
                'total_response_ms' => (int) $mine->sum('response_ms'),
            ];
        })->sort(fn (array $a, array $b) => [$b['score'], $a['total_response_ms']] <=> [$a['score'], $b['total_response_ms']])->values();

        foreach ($rows as $index => $row) {
            GameResult::query()->create($row + ['rank' => $index + 1]);
        }

        $game->status = Game::COMPLETED;
        $game->ended_at = now();
        $game->save();

        Room::query()->whereKey($game->room_id)->update(['status' => RoomStatus::Completed->value]);

        // Achievements never block the end of a game: a failure is reported, not thrown.
        rescue(fn () => app(AchievementService::class)->awardFor($game), report: true);
    }

    /**
     * Running scores over closed questions, best first.
     *
     * @return array<int, array{user_id: int, name: string|null, score: int}>
     */
    private function leaderboard(Game $game): array
    {
        $scores = PlayerAnswer::query()
            ->join('game_questions', 'game_questions.id', '=', 'player_answers.game_question_id')
            ->where('game_questions.game_id', $game->id)
            ->whereNotNull('game_questions.closed_at')
            ->groupBy('player_answers.user_id')
            ->selectRaw('player_answers.user_id, sum(player_answers.points) as score, sum(player_answers.response_ms) as time')
            ->get()
            ->keyBy('user_id');

        return RoomPlayer::query()->where('room_id', $game->room_id)->with('user:id,name')->get()
            ->map(fn (RoomPlayer $membership) => [
                'user_id' => $membership->user_id,
                'name' => $membership->user?->name,
                'score' => (int) ($scores[$membership->user_id]->score ?? 0),
                'time' => (int) ($scores[$membership->user_id]->time ?? 0),
                'left' => $membership->left_at !== null,
            ])
            ->sort(fn (array $a, array $b) => [$b['score'], $a['time']] <=> [$a['score'], $b['time']])
            ->values()
            ->all();
    }

    /**
     * The game language's approved text. If a moderator withdrew that approval
     * mid-game, fall back to the approved origin text, and only then to the
     * text that was approved when the game picked the question.
     */
    private function translationFor(Question $question, string $language): ?QuestionTranslation
    {
        $approved = $question->translations()->where('status', TranslationStatus::Approved->value)->get()->keyBy('language');

        return $approved->get($language)
            ?? $approved->get($question->origin_language)
            ?? $question->translations()->where('language', $language)->first();
    }
}

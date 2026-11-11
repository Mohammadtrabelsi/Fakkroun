<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\AdDelivery;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Creative;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Gameplay\GameQuestionService;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Models\Category;
use App\Models\User;
use App\Services\CategoryService;
use App\Services\PlatformSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-game ads (spec §17–§18.2, story 16).
 *
 * Placements, all at calm moments, never while a question is open (S3):
 *   - reveal:    under the answer explanation, once a question is closed;
 *   - post_game: on the results page, once per player and room;
 *   - lobby:     in the waiting room, once per player and room.
 *
 *  1. offer*(): maybe pick an approved creative for this player (paid
 *     accounts, frequency caps, relevance, credits) and issue a single-use
 *     delivery token. Nothing is billed here.
 *  2. confirm(): the player's browser reports the ad was actually on
 *     screen, with that token. Only then is ONE impression recorded and
 *     billed, under billing reference "impression:<delivery id>", so
 *     refreshes, reconnects and repeated reports can never bill twice.
 */
class AdService
{
    public const PLACEMENTS = ['reveal', 'post_game', 'lobby'];

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly WalletService $wallets,
        private readonly CampaignService $campaigns,
        private readonly AdDeliveryService $adDeliveries,
        private readonly CategoryService $categories,
        private readonly ImpressionService $impressions,
        private readonly GameQuestionService $gameQuestions,
        private readonly RoomService $rooms,
    ) {}

    /** Ad under the answer of a closed question. */
    public function offer(Room $room, GameQuestion $gq, User $player): ?AdDelivery
    {
        return $this->offerAt($room, $player, 'reveal', $gq);
    }

    /** Ad on the results page, after the game. */
    public function offerPostGame(Room $room, User $player): ?AdDelivery
    {
        return $this->offerAt($room, $player, 'post_game');
    }

    /** Ad in the waiting room, before the game starts. */
    public function offerLobby(Room $room, User $player): ?AdDelivery
    {
        return $this->offerAt($room, $player, 'lobby');
    }

    private function offerAt(Room $room, User $player, string $placement, ?GameQuestion $gq = null): ?AdDelivery
    {
        $slot = $placement === 'reveal' ? 'reveal:'.$gq?->id : $placement.':'.$room->id;

        if ($existing = $this->delivery($player, $slot)) {
            return $existing;
        }

        // Paid accounts are ad-free, and so is every room a paid account hosts.
        if ($player->hasPaidPlan() || $room->host?->hasPaidPlan()) {
            return null;
        }

        $ads = $this->settings->all()['advertising'];

        if (! $ads['enabled'] || ! ($ads[$placement.'_enabled'] ?? true)) {
            return null;
        }

        if ($placement === 'reveal' && ! $this->revealAllowed($room, $gq, $player, $ads)) {
            return null;
        }

        $creative = $this->pick($this->context($room, $gq), $player, $room->id, $ads);

        if ($creative === null) {
            return null;
        }

        try {
            return $this->adDeliveries->query()->create([
                'campaign_id' => $creative->campaign_id,
                'creative_id' => $creative->id,
                'room_id' => $room->id,
                'game_question_id' => $gq?->id,
                'player_id' => $player->id,
                'placement' => $placement,
                'slot' => $slot,
                'token' => Str::random(48),
                'issued_at' => now(),
                'expires_at' => now()->addMinutes(max(1, (int) ($ads['delivery_minutes'] ?? 10))),
            ]);
        } catch (UniqueConstraintViolationException) {
            return $this->delivery($player, $slot);
        }
    }

    /**
     * Reveal-only rules: one question in N, a cap per player per game, and a
     * minimum gap between two reveal ads.
     *
     * @param  array<string, mixed>  $ads
     */
    private function revealAllowed(Room $room, ?GameQuestion $gq, User $player, array $ads): bool
    {
        if ($gq === null || $gq->position % max(1, (int) $ads['every_n_questions']) !== 0) {
            return false;
        }

        $mine = $this->adDeliveries->query()->where('player_id', $player->id)->where('room_id', $room->id)->where('placement', 'reveal');

        if ((clone $mine)->count() >= (int) $ads['max_per_player_per_game']) {
            return false;
        }

        $last = (clone $mine)->max('issued_at');

        return $last === null || now()->diffInSeconds($last, true) >= (int) $ads['min_seconds_between'];
    }

    /**
     * What the ad is matched against (S1): the question for a reveal, the
     * room's topics otherwise. Never anything about the player.
     *
     * @return array{category_ids: array<int, int>, language: string, text: string}
     */
    private function context(Room $room, ?GameQuestion $gq): array
    {
        if ($gq !== null) {
            $question = $gq->question;
            $language = (string) $gq->language;

            return [
                'category_ids' => array_values(array_filter([$question?->category_id, $question?->subcategory_id])),
                'language' => $language,
                'text' => implode(' ', array_filter([
                    $question?->translation($language)?->body,
                    $question?->category?->getTranslation('name', $language, false),
                    $question?->subcategory?->getTranslation('name', $language, false),
                ])),
            ];
        }

        $language = (string) $room->setting('language');
        $categoryIds = array_map('intval', (array) $room->setting('category_ids'));

        return [
            'category_ids' => $categoryIds,
            'language' => $language,
            'text' => $this->categories->query()->whereIn('id', $categoryIds)->get()
                ->map(fn (Category $c) => $c->getTranslation('name', $language, false))
                ->filter()->implode(' '),
        ];
    }

    /**
     * Records and bills the impression for a confirmed display. Returns null
     * when the token is unknown, expired, belongs to someone else, or the
     * campaign ran out of credits (which then stops the campaign).
     */
    public function confirm(string $token, User $player): ?Impression
    {
        $delivery = $this->adDeliveries->query()->with(['campaign.advertiser'])->where('token', $token)->first();

        if ($delivery === null || $delivery->player_id !== $player->id || $delivery->expires_at->isPast()) {
            return null;
        }

        if ($existing = $this->impressions->query()->where('delivery_id', $delivery->id)->first()) {
            return $existing;
        }

        $reference = 'impression:'.$delivery->id;
        $gq = $delivery->game_question_id ? $this->gameQuestions->query()->find($delivery->game_question_id) : null;
        $room = $gq ? null : $this->rooms->query()->find($delivery->room_id);

        try {
            return DB::transaction(function () use ($delivery, $reference, $gq, $room): Impression {
                $entry = $this->wallets->consume($delivery->campaign->advertiser, CreditType::Impression, 1, $reference, [
                    'campaign_id' => $delivery->campaign_id,
                    'format' => $delivery->placement === 'reveal' ? 'banner' : $delivery->placement,
                    'room_id' => $delivery->room_id,
                    'game_question_id' => $delivery->game_question_id,
                    'question_id' => $gq?->question_id,
                    'player_id' => $delivery->player_id,
                ]);

                return $this->impressions->query()->firstOrCreate(['delivery_id' => $delivery->id], [
                    'campaign_id' => $delivery->campaign_id,
                    'creative_id' => $delivery->creative_id,
                    'player_id' => $delivery->player_id,
                    'room_id' => $delivery->room_id,
                    'placement' => $delivery->placement,
                    'game_question_id' => $delivery->game_question_id,
                    'language' => $gq?->language ?? $room?->setting('language'),
                    'category_id' => $gq?->question?->category_id ?? (($first = ((array) $room?->setting('category_ids'))[0] ?? null) !== null ? (int) $first : null),
                    'billing_reference' => $reference,
                    'ledger_entry_id' => $entry->id,
                    'displayed_at' => now(),
                ]);
            });
        } catch (InsufficientCredits) {
            $this->campaigns->markExhausted($delivery->campaign);

            return null;
        } catch (UniqueConstraintViolationException) {
            return $this->impressions->query()->where('delivery_id', $delivery->id)->first();
        }
    }

    private function delivery(User $player, string $slot): ?AdDelivery
    {
        return $this->adDeliveries->query()->where('player_id', $player->id)->where('slot', $slot)->first();
    }

    /**
     * The most relevant creative (story 16 S1), skipping creatives this
     * player has already seen too often today (S4) and campaigns that hit
     * their per-player cap in this room. Ties go to the creative this player
     * has seen least today, then at random, so a campaign's creatives rotate.
     *
     * @param  array{category_ids: array<int, int>, language: string, text: string}  $context
     * @param  array<string, mixed>  $ads
     */
    private function pick(array $context, User $player, int $roomId, array $ads): ?Creative
    {
        $language = $context['language'];
        $campaigns = $this->campaigns->query()->running()
            ->whereHas('advertiser.wallets', fn ($q) => $q->where('credit_type', CreditType::Impression->value)->where('balance', '>', 0))
            ->with(['creatives' => fn ($q) => $q->where('status', 'approved')->whereIn('format', ['banner', 'logo'])
                ->where(fn ($w) => $w->whereNull('language')->orWhere('language', $language))])
            ->get()
            ->filter(fn (Campaign $c) => $c->targetsAny($context['category_ids'], $language))
            ->filter(fn (Campaign $c) => $this->adDeliveries->query()->where('campaign_id', $c->id)->where('player_id', $player->id)->where('room_id', $roomId)->count() < $c->max_per_player_per_game);

        $creativeIds = $campaigns->flatMap(fn (Campaign $c) => $c->creatives->pluck('id'))->all();

        if ($creativeIds === []) {
            return null;
        }

        $seenToday = $this->adDeliveries->query()
            ->where('player_id', $player->id)
            ->whereIn('creative_id', $creativeIds)
            ->where('issued_at', '>=', now()->startOfDay())
            ->selectRaw('creative_id, count(*) as total')
            ->groupBy('creative_id')
            ->pluck('total', 'creative_id');

        $cap = max(1, (int) ($ads['max_same_creative_per_day'] ?? 3));
        $allowUntargeted = (bool) ($ads['allow_untargeted'] ?? true);
        $relevance = app(AdRelevance::class);
        // S5: better-performing creatives get a bonus; new ones count as average.
        $performance = app(CreativePerformance::class)->ratios(array_map('intval', $creativeIds), (int) ($ads['performance_min_impressions'] ?? 100));
        $performanceWeight = (float) ($ads['weight_performance'] ?? 0);

        return $campaigns
            ->flatMap(fn (Campaign $c) => $c->creatives->map(fn (Creative $creative) => [
                'creative' => $creative,
                'score' => $score = $relevance->score($c, $creative, $context),
                // Relevance decides eligibility; performance only reorders.
                'rank' => $score + $performanceWeight * ($performance[$creative->id] ?? 1.0),
                'seen' => (int) ($seenToday[$creative->id] ?? 0),
                'tiebreak' => random_int(0, PHP_INT_MAX),
            ]))
            ->filter(fn (array $row) => $row['seen'] < $cap && ($row['score'] > 0 || $allowUntargeted))
            ->sort(fn (array $a, array $b) => [$b['rank'], $a['seen'], $a['tiebreak']] <=> [$a['rank'], $b['seen'], $b['tiebreak']])
            ->first()['creative'] ?? null;
    }
}

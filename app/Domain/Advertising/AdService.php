<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\AdDelivery;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Creative;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\Room;
use App\Models\Category;
use App\Models\User;
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
            return AdDelivery::query()->create([
                'campaign_id' => $creative->campaign_id,
                'creative_id' => $creative->id,
                'room_id' => $room->id,
                'game_question_id' => $gq?->id,
                'player_id' => $player->id,
                'placement' => $placement,
                'slot' => $slot,
                'token' => Str::random(48),
                'issued_at' => now(),
                'expires_at' => now()->addMinutes(10),
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

        $mine = AdDelivery::query()->where('player_id', $player->id)->where('room_id', $room->id)->where('placement', 'reveal');

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
            'text' => Category::query()->whereIn('id', $categoryIds)->get()
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
        $delivery = AdDelivery::query()->with(['campaign.advertiser'])->where('token', $token)->first();

        if ($delivery === null || $delivery->player_id !== $player->id || $delivery->expires_at->isPast()) {
            return null;
        }

        if ($existing = Impression::query()->where('delivery_id', $delivery->id)->first()) {
            return $existing;
        }

        $reference = 'impression:'.$delivery->id;
        $gq = $delivery->game_question_id ? GameQuestion::query()->find($delivery->game_question_id) : null;
        $room = $gq ? null : Room::query()->find($delivery->room_id);

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

                return Impression::query()->firstOrCreate(['delivery_id' => $delivery->id], [
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
            return Impression::query()->where('delivery_id', $delivery->id)->first();
        }
    }

    private function delivery(User $player, string $slot): ?AdDelivery
    {
        return AdDelivery::query()->where('player_id', $player->id)->where('slot', $slot)->first();
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
        $campaigns = Campaign::query()->running()
            ->whereHas('advertiser.wallets', fn ($q) => $q->where('credit_type', CreditType::Impression->value)->where('balance', '>', 0))
            ->with(['creatives' => fn ($q) => $q->where('status', 'approved')->whereIn('format', ['banner', 'logo'])
                ->where(fn ($w) => $w->whereNull('language')->orWhere('language', $language))])
            ->get()
            ->filter(fn (Campaign $c) => $c->targetsAny($context['category_ids'], $language))
            ->filter(fn (Campaign $c) => AdDelivery::query()->where('campaign_id', $c->id)->where('player_id', $player->id)->where('room_id', $roomId)->count() < $c->max_per_player_per_game);

        $creativeIds = $campaigns->flatMap(fn (Campaign $c) => $c->creatives->pluck('id'))->all();

        if ($creativeIds === []) {
            return null;
        }

        $seenToday = AdDelivery::query()
            ->where('player_id', $player->id)
            ->whereIn('creative_id', $creativeIds)
            ->where('issued_at', '>=', now()->startOfDay())
            ->selectRaw('creative_id, count(*) as total')
            ->groupBy('creative_id')
            ->pluck('total', 'creative_id');

        $cap = max(1, (int) ($ads['max_same_creative_per_day'] ?? 3));
        $allowUntargeted = (bool) ($ads['allow_untargeted'] ?? true);
        $relevance = app(AdRelevance::class);

        return $campaigns
            ->flatMap(fn (Campaign $c) => $c->creatives->map(fn (Creative $creative) => [
                'creative' => $creative,
                'score' => $relevance->score($c, $creative, $context),
                'seen' => (int) ($seenToday[$creative->id] ?? 0),
                'tiebreak' => random_int(0, PHP_INT_MAX),
            ]))
            ->filter(fn (array $row) => $row['seen'] < $cap && ($row['score'] > 0 || $allowUntargeted))
            ->sort(fn (array $a, array $b) => [$b['score'], $a['seen'], $a['tiebreak']] <=> [$a['score'], $b['seen'], $b['tiebreak']])
            ->first()['creative'] ?? null;
    }
}

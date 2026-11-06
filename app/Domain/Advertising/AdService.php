<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\AdDelivery;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Creative;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\Room;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * In-game ads (spec §17–§18.2).
 *
 *  1. offer(): when a question's results are shown, maybe pick an approved
 *     creative for this player (frequency caps, targeting, credits) and
 *     issue a single-use delivery token. Nothing is billed here.
 *  2. confirm(): the player's browser reports the ad was actually on
 *     screen, with that token. Only then is ONE impression recorded and
 *     billed, under billing reference "impression:<delivery id>", so
 *     refreshes, reconnects and repeated reports can never bill twice.
 */
class AdService
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly WalletService $wallets,
        private readonly CampaignService $campaigns,
    ) {}

    public function offer(Room $room, GameQuestion $gq, User $player): ?AdDelivery
    {
        $existing = AdDelivery::query()->where('player_id', $player->id)->where('game_question_id', $gq->id)->where('placement', 'reveal')->first();

        if ($existing) {
            return $existing;
        }

        // Paid accounts are ad-free, and so is every room a paid account hosts.
        if ($player->hasPaidPlan() || $room->host?->hasPaidPlan()) {
            return null;
        }

        $ads = $this->settings->all()['advertising'];

        if (! $ads['enabled'] || $gq->position % max(1, (int) $ads['every_n_questions']) !== 0) {
            return null;
        }

        $gameQuestionIds = GameQuestion::query()->where('game_id', $gq->game_id)->pluck('id');
        $mine = AdDelivery::query()->where('player_id', $player->id)->whereIn('game_question_id', $gameQuestionIds);

        if ((clone $mine)->count() >= (int) $ads['max_per_player_per_game']) {
            return null;
        }

        $last = (clone $mine)->max('issued_at');

        if ($last !== null && now()->diffInSeconds($last, true) < (int) $ads['min_seconds_between']) {
            return null;
        }

        $question = $gq->question;
        $language = (string) $gq->language;
        $context = [
            'category_id' => $question?->category_id,
            'subcategory_id' => $question?->subcategory_id,
            'language' => $language,
            // Words of the question and its topic names, for keyword matching (S1).
            'text' => implode(' ', array_filter([
                $question?->translation($language)?->body,
                $question?->category?->getTranslation('name', $language, false),
                $question?->subcategory?->getTranslation('name', $language, false),
            ])),
        ];

        $creative = $this->pick($context, $player, $gameQuestionIds->all(), $ads);

        if ($creative === null) {
            return null;
        }

        try {
            return AdDelivery::query()->create([
                'campaign_id' => $creative->campaign_id,
                'creative_id' => $creative->id,
                'room_id' => $room->id,
                'game_question_id' => $gq->id,
                'player_id' => $player->id,
                'placement' => 'reveal',
                'token' => Str::random(48),
                'issued_at' => now(),
                'expires_at' => now()->addMinutes(10),
            ]);
        } catch (UniqueConstraintViolationException) {
            return AdDelivery::query()->where('player_id', $player->id)->where('game_question_id', $gq->id)->where('placement', 'reveal')->first();
        }
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
        $gq = GameQuestion::query()->find($delivery->game_question_id);

        try {
            return DB::transaction(function () use ($delivery, $reference, $gq): Impression {
                $entry = $this->wallets->consume($delivery->campaign->advertiser, CreditType::Impression, 1, $reference, [
                    'campaign_id' => $delivery->campaign_id,
                    'format' => 'banner',
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
                    'game_question_id' => $delivery->game_question_id,
                    'language' => $gq?->language,
                    'category_id' => $gq?->question?->category_id,
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

    /**
     * @param  array<int, int>  $gameQuestionIds
     */
    /**
     * The most relevant creative for this question (story 16 S1), skipping
     * creatives this player has already seen too often today (S4). Ties go
     * to the creative this player has seen least today, then at random, so
     * a campaign's creatives rotate.
     *
     * @param  array{category_id: ?int, subcategory_id: ?int, language: string, text: string}  $context
     * @param  array<int, int>  $gameQuestionIds
     * @param  array<string, mixed>  $ads
     */
    private function pick(array $context, User $player, array $gameQuestionIds, array $ads): ?Creative
    {
        $language = $context['language'];
        $campaigns = Campaign::query()->running()
            ->whereHas('advertiser.wallets', fn ($q) => $q->where('credit_type', CreditType::Impression->value)->where('balance', '>', 0))
            ->with(['creatives' => fn ($q) => $q->where('status', 'approved')->whereIn('format', ['banner', 'logo'])
                ->where(fn ($w) => $w->whereNull('language')->orWhere('language', $language))])
            ->get()
            ->filter(fn (Campaign $c) => $c->targets($context['category_id'], $language, $context['subcategory_id']))
            ->filter(fn (Campaign $c) => AdDelivery::query()->where('campaign_id', $c->id)->where('player_id', $player->id)->whereIn('game_question_id', $gameQuestionIds)->count() < $c->max_per_player_per_game);

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

<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\SponsoredRoundDelivery;
use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\RoomPlayer;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionPoolEntry;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sponsored rounds (story 16 S12): a block of advertising.sponsored_round_size
 * consecutive questions, all assigned to one campaign (Admin → question
 * editor → Sponsorship) and reviewed like any other question. The round is
 * introduced with "This round is presented by …" and billed ONCE per room,
 * from the separate sponsored_round credit balance, when its first question
 * opens (billing reference "sponsored-round:<game id>").
 */
class SponsoredRoundService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly CampaignService $campaigns,
    ) {}

    /**
     * A round for a new game, or null.
     *
     * @param  array<int, int>  $categoryIds
     * @return array{campaign_id: int, question_ids: array<int, int>}|null
     */
    public function pick(string $language, array $categoryIds, ?string $difficulty, int $size): ?array
    {
        if ($size < 2) {
            return null;
        }

        $campaigns = Campaign::query()->running()
            ->whereHas('advertiser.wallets', fn ($q) => $q->where('credit_type', CreditType::SponsoredRound->value)->where('balance', '>', 0))
            ->get()
            ->filter(fn (Campaign $c) => $c->targetsAny($categoryIds, $language))
            ->shuffle();

        foreach ($campaigns as $campaign) {
            $ids = QuestionPoolEntry::query()
                ->where('language', $language)
                ->where('is_sponsored', true)
                ->when($categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds))
                ->when($difficulty !== null, fn ($q) => $q->where('difficulty', $difficulty))
                ->whereIn('question_id', Question::query()->where('campaign_id', $campaign->id)->select('id'))
                ->inRandomOrder()
                ->limit($size)
                ->pluck('question_id')
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values()
                ->all();

            if (count($ids) === $size) {
                return ['campaign_id' => $campaign->id, 'question_ids' => $ids];
            }
        }

        return null;
    }

    /** First question of the round: the one that bills and shows the intro. */
    public function isRoundStart(GameQuestion $gq): bool
    {
        return $gq->sponsored_round_campaign_id !== null
            && ! GameQuestion::query()->where('game_id', $gq->game_id)
                ->where('sponsored_round_campaign_id', $gq->sponsored_round_campaign_id)
                ->where('position', '<', $gq->position)->exists();
    }

    public function sponsorName(?int $campaignId): ?string
    {
        return $campaignId ? Campaign::query()->with('advertiser:id,name')->find($campaignId)?->advertiser?->name : null;
    }

    /**
     * Bills the round to the room, once. If credits ran out since the game
     * started, the round is still played, not billed, and the campaign stops.
     */
    public function recordDelivery(GameQuestion $gq): ?SponsoredRoundDelivery
    {
        if (! $this->isRoundStart($gq)) {
            return null;
        }

        if ($existing = SponsoredRoundDelivery::query()->where('game_id', $gq->game_id)->first()) {
            return $existing;
        }

        $campaign = Campaign::query()->with('advertiser')->find($gq->sponsored_round_campaign_id);
        $game = Game::query()->find($gq->game_id);

        if ($campaign === null || $game === null) {
            return null;
        }

        $reference = 'sponsored-round:'.$game->id;
        $question = Question::withTrashed()->find($gq->question_id);

        try {
            $entry = $this->wallets->consume($campaign->advertiser, CreditType::SponsoredRound, 1, $reference, [
                'campaign_id' => $campaign->id,
                'format' => 'sponsored_round',
                'room_id' => $game->room_id,
                'game_question_id' => $gq->id,
                'question_id' => $gq->question_id,
            ]);

            return SponsoredRoundDelivery::query()->create([
                'game_id' => $game->id,
                'campaign_id' => $campaign->id,
                'room_id' => $game->room_id,
                'questions_count' => GameQuestion::query()->where('game_id', $game->id)->where('sponsored_round_campaign_id', $campaign->id)->count(),
                'players_count' => RoomPlayer::query()->where('room_id', $game->room_id)->whereNull('left_at')->count(),
                'language' => $gq->language,
                'category_id' => $question?->category_id,
                'billing_reference' => $reference,
                'ledger_entry_id' => $entry->id,
                'delivered_at' => now(),
            ]);
        } catch (InsufficientCredits) {
            $this->campaigns->markExhausted($campaign);

            return null;
        } catch (UniqueConstraintViolationException) {
            return SponsoredRoundDelivery::query()->where('game_id', $game->id)->first();
        }
    }
}

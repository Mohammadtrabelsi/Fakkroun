<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\SponsoredDelivery;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\RoomPlayer;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionPoolEntry;
use App\Domain\Questions\QuestionPool;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;

/**
 * Sponsored questions (spec §17.3, §18.3). They are normal, moderated
 * questions linked to a campaign: same rules, same scoring, clearly
 * labelled, limited per game. Billing is ONE sponsored-question credit per
 * room delivery (not per player), under "sponsored:<game question id>".
 */
class SponsoredQuestionService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly CampaignService $campaigns,
        private readonly QuestionPool $pool,
    ) {}

    public function assign(Question $question, ?Campaign $campaign, User $admin): Question
    {
        if (! $admin->can('manage advertising')) {
            throw new AuthorizationException;
        }

        $question->forceFill(['is_sponsored' => $campaign !== null, 'campaign_id' => $campaign?->id])->save();
        $this->pool->sync($question);

        return $question;
    }

    /**
     * Sponsored question ids for a new game.
     *
     * @param  array<int, int>  $categoryIds
     * @return array<int, int>
     */
    public function pick(string $language, array $categoryIds, ?string $difficulty, int $limit): array
    {
        if ($limit <= 0) {
            return [];
        }

        $campaignIds = Campaign::query()->running()
            ->whereHas('advertiser.wallets', fn ($q) => $q->where('credit_type', CreditType::SponsoredQuestion->value)->where('balance', '>', 0))
            ->get()
            ->filter(fn (Campaign $c) => $c->targets(null, $language))
            ->pluck('id');

        if ($campaignIds->isEmpty()) {
            return [];
        }

        return QuestionPoolEntry::query()
            ->where('language', $language)
            ->where('is_sponsored', true)
            ->when($categoryIds !== [], fn ($q) => $q->whereIn('category_id', $categoryIds))
            ->when($difficulty !== null, fn ($q) => $q->where('difficulty', $difficulty))
            ->whereIn('question_id', Question::query()->whereIn('campaign_id', $campaignIds)->select('id'))
            ->inRandomOrder()
            ->limit($limit)
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Bills the delivery of a sponsored question to a room, once.
     */
    public function recordDelivery(GameQuestion $gq): ?SponsoredDelivery
    {
        $question = Question::withTrashed()->find($gq->question_id);

        if (! $question?->is_sponsored || $question->campaign_id === null) {
            return null;
        }

        if ($existing = SponsoredDelivery::query()->where('game_question_id', $gq->id)->first()) {
            return $existing;
        }

        $campaign = Campaign::query()->with('advertiser')->find($question->campaign_id);
        $game = $gq->game;

        if ($campaign === null) {
            return null;
        }

        $reference = 'sponsored:'.$gq->id;

        try {
            $entry = $this->wallets->consume($campaign->advertiser, CreditType::SponsoredQuestion, 1, $reference, [
                'campaign_id' => $campaign->id,
                'format' => 'sponsored_question',
                'room_id' => $game->room_id,
                'game_question_id' => $gq->id,
                'question_id' => $question->id,
            ]);

            return SponsoredDelivery::query()->create([
                'game_question_id' => $gq->id,
                'campaign_id' => $campaign->id,
                'question_id' => $question->id,
                'room_id' => $game->room_id,
                'players_count' => RoomPlayer::query()->where('room_id', $game->room_id)->whereNull('left_at')->count(),
                'language' => $gq->language,
                'category_id' => $question->category_id,
                'billing_reference' => $reference,
                'ledger_entry_id' => $entry->id,
                'delivered_at' => now(),
            ]);
        } catch (InsufficientCredits) {
            // Already selected for this game: it is played, but not billed, and the campaign stops.
            $this->campaigns->markExhausted($campaign);

            return null;
        } catch (UniqueConstraintViolationException) {
            return SponsoredDelivery::query()->where('game_question_id', $gq->id)->first();
        }
    }
}

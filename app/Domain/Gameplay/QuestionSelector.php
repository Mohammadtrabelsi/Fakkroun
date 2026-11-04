<?php

namespace App\Domain\Gameplay;

use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\PlayerAnswer;
use App\Domain\Questions\Models\QuestionPoolEntry;
use Illuminate\Support\Facades\DB;

/**
 * Picks questions for a game from question_language_pool (spec §16) without
 * ever loading the bank: each draw is an index seek from a random point on
 * random_key, wrapping around once. Questions the players answered recently
 * are avoided first; if that leaves too few, they are allowed again.
 * Chosen rows get a new random_key and last_served_at, which spreads usage
 * across rooms.
 */
class QuestionSelector
{
    /**
     * @param  array<int, int>  $categoryIds  top-level category ids
     * @param  array<int, int>  $playerIds
     * @return array<int, int>  question ids, in play order
     */
    public function select(string $language, array $categoryIds, ?string $difficulty, int $count, array $playerIds = []): array
    {
        $recent = $this->recentlySeen($playerIds);
        $chosen = $this->draw($language, $categoryIds, $difficulty, $count, $recent);

        if (count($chosen) < $count && $recent !== []) {
            $chosen = [...$chosen, ...$this->draw($language, $categoryIds, $difficulty, $count - count($chosen), $chosen)];
        }

        if ($chosen !== []) {
            QuestionPoolEntry::query()->whereIn('question_id', $chosen)->where('language', $language)->get()
                ->each(fn (QuestionPoolEntry $entry) => $entry->forceFill([
                    'random_key' => mt_rand() / mt_getrandmax(),
                    'last_served_at' => now(),
                ])->save());
        }

        shuffle($chosen);

        return $chosen;
    }

    /**
     * Pool entries available for these filters (for the room form).
     *
     * @param  array<int, int>  $categoryIds
     */
    public function available(string $language, array $categoryIds, ?string $difficulty): int
    {
        return $this->baseQuery($language, $categoryIds, $difficulty)->count();
    }

    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, int>  $exclude
     * @return array<int, int>
     */
    private function draw(string $language, array $categoryIds, ?string $difficulty, int $count, array $exclude): array
    {
        $pivot = mt_rand() / mt_getrandmax();
        $ids = [];

        foreach ([['>=', 'asc'], ['<', 'asc']] as [$operator, $direction]) {
            if (count($ids) >= $count) {
                break;
            }

            $ids = [...$ids, ...$this->baseQuery($language, $categoryIds, $difficulty)
                ->where('random_key', $operator, $pivot)
                ->when([...$exclude, ...$ids] !== [], fn ($query) => $query->whereNotIn('question_id', [...$exclude, ...$ids]))
                ->orderBy('random_key', $direction)
                ->limit($count - count($ids))
                ->pluck('question_id')
                ->map(fn ($id) => (int) $id)
                ->all()];
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array<int, int>  $categoryIds
     */
    private function baseQuery(string $language, array $categoryIds, ?string $difficulty)
    {
        return QuestionPoolEntry::query()
            ->where('language', $language)
            ->when($categoryIds !== [], fn ($query) => $query->whereIn('category_id', $categoryIds))
            ->when($difficulty !== null && $difficulty !== 'any', fn ($query) => $query->where('difficulty', $difficulty));
    }

    /**
     * Questions any of these players answered recently (bounded query).
     *
     * @param  array<int, int>  $playerIds
     * @return array<int, int>
     */
    private function recentlySeen(array $playerIds): array
    {
        if ($playerIds === []) {
            return [];
        }

        return DB::table((new PlayerAnswer)->getTable().' as pa')
            ->join((new GameQuestion)->getTable().' as gq', 'gq.id', '=', 'pa.game_question_id')
            ->whereIn('pa.user_id', $playerIds)
            ->where('pa.received_at', '>=', now()->subDays((int) config('fakkroun.gameplay.recent_question_days', 30)))
            ->distinct()
            ->limit(2000)
            ->pluck('gq.question_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
}

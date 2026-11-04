<?php

namespace App\Domain\Gameplay;

/**
 * Duplicate-answer rule (spec §14): when at least $threshold players give
 * the same answer to a question, none of those answers score. It applies to
 * every answer alike, correct or not, and to every player alike.
 *
 * Example (threshold 2): A→B, B→B, C→A, D→C. Both "B" answers are excluded;
 * A and C are unique and scored normally.
 */
final class DuplicateAnswerRule
{
    /**
     * @param  array<int, int>  $choiceByPlayer  player id => chosen choice id
     * @return array<int, int>  choice ids whose answers are excluded
     */
    public static function excludedChoices(array $choiceByPlayer, int $threshold): array
    {
        if ($threshold < 2) {
            return [];
        }

        $counts = array_count_values(array_map('intval', $choiceByPlayer));

        return array_keys(array_filter($counts, fn (int $count) => $count >= $threshold));
    }
}

<?php

namespace App\Domain\Gameplay;

/**
 * Points for one answer (spec §15). Pure: every input comes from the game's
 * frozen settings and the server-measured response time.
 */
final class ScoringEngine
{
    /**
     * @param  array<string, mixed>  $settings  points_easy|medium|hard, speed_bonus_max
     */
    public static function points(bool $correct, bool $excluded, string $difficulty, int $responseMs, int $durationMs, array $settings): int
    {
        if (! $correct || $excluded) {
            return 0;
        }

        $base = (int) ($settings['points_'.$difficulty] ?? 0);
        $maxBonus = (int) ($settings['speed_bonus_max'] ?? 0);

        if ($maxBonus <= 0 || $durationMs <= 0) {
            return $base;
        }

        $remaining = max(0.0, 1 - min($responseMs, $durationMs) / $durationMs);

        return $base + (int) round($maxBonus * $remaining);
    }
}

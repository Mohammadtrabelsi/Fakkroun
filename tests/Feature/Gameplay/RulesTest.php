<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\DuplicateAnswerRule;
use App\Domain\Gameplay\ScoringEngine;
use PHPUnit\Framework\TestCase;

class RulesTest extends TestCase
{
    public function test_spec_example_excludes_both_b_answers(): void
    {
        // Spec §14: A→B, B→B, C→A, D→C.
        $this->assertSame([20], DuplicateAnswerRule::excludedChoices([1 => 20, 2 => 20, 3 => 10, 4 => 30], 2));
    }

    public function test_threshold_is_configurable(): void
    {
        $answers = [1 => 7, 2 => 7, 3 => 7, 4 => 8, 5 => 8];

        $this->assertEqualsCanonicalizing([7, 8], DuplicateAnswerRule::excludedChoices($answers, 2));
        $this->assertSame([7], DuplicateAnswerRule::excludedChoices($answers, 3));
        $this->assertSame([], DuplicateAnswerRule::excludedChoices($answers, 4));
    }

    public function test_unique_answers_and_disabled_thresholds_exclude_nothing(): void
    {
        $this->assertSame([], DuplicateAnswerRule::excludedChoices([1 => 1, 2 => 2, 3 => 3], 2));
        $this->assertSame([], DuplicateAnswerRule::excludedChoices([1 => 1, 2 => 1], 1));
        $this->assertSame([], DuplicateAnswerRule::excludedChoices([], 2));
    }

    public function test_scoring_by_difficulty_and_speed(): void
    {
        $settings = ['points_easy' => 10, 'points_medium' => 20, 'points_hard' => 30, 'speed_bonus_max' => 10];

        $this->assertSame(20, ScoringEngine::points(true, false, 'easy', 0, 20000, $settings));
        $this->assertSame(15, ScoringEngine::points(true, false, 'easy', 10000, 20000, $settings));
        $this->assertSame(30, ScoringEngine::points(true, false, 'hard', 20000, 20000, $settings));
        $this->assertSame(30, ScoringEngine::points(true, false, 'hard', 99999, 20000, $settings));
        $this->assertSame(0, ScoringEngine::points(false, false, 'hard', 0, 20000, $settings));
        $this->assertSame(0, ScoringEngine::points(true, true, 'hard', 0, 20000, $settings));
        $this->assertSame(20, ScoringEngine::points(true, false, 'medium', 0, 20000, ['points_medium' => 20]));
    }
}

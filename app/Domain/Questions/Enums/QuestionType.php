<?php

namespace App\Domain\Questions\Enums;

/**
 * Only four-choice multiple choice exists today (spec §7.1); the column
 * keeps room for future formats without a schema change.
 */
enum QuestionType: string
{
    case MultipleChoice = 'mcq';

    public function choiceCount(): int
    {
        return match ($this) {
            self::MultipleChoice => 4,
        };
    }
}

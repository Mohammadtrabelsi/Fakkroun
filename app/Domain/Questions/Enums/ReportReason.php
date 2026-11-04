<?php

namespace App\Domain\Questions\Enums;

enum ReportReason: string
{
    case WrongAnswer = 'wrong_answer';
    case Outdated = 'outdated';
    case Unclear = 'unclear';
    case Translation = 'translation';
    case Inappropriate = 'inappropriate';
    case Duplicate = 'duplicate';
    case Other = 'other';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

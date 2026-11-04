<?php

namespace App\Domain\Questions\Enums;

/**
 * Where a question came from (spec §7.5). AI questions arrive in Phase 4.
 */
enum QuestionSource: string
{
    case Admin = 'admin';
    case Community = 'community';
    case Ai = 'ai';
    case Import = 'import';
    case Partner = 'partner';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

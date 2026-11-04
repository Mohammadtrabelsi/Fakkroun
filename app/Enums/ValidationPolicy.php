<?php

namespace App\Enums;

/**
 * Who may approve questions in a category (spec §7.4). Configured per
 * category by administrators; enforced by the moderation workflow (Phase 2).
 */
enum ValidationPolicy: string
{
    /** Only administrators approve. Default for factual / sensitive topics. */
    case Admin = 'admin';

    /** Administrators or the category's assigned moderators. */
    case Moderator = 'moderator';

    /** Moderators, plus community validation where the platform enables it. */
    case Community = 'community';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

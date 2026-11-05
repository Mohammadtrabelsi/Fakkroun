<?php

namespace App\Domain\Questions\Enums;

/**
 * Question lifecycle (spec §7.3):
 * Draft → Pending → Under review → Approved / Rejected → Published ⇄ Unpublished.
 * A reviewer may instead send it back as "changes requested" with a note;
 * the author edits and resubmits.
 * Transitions are enforced by ModerationService, never set directly.
 */
enum QuestionStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case UnderReview = 'under_review';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case ChangesRequested = 'changes_requested';
    case Published = 'published';
    case Unpublished = 'unpublished';

    /**
     * Statuses a moderator still has to act on.
     *
     * @return array<int, self>
     */
    public static function awaitingReview(): array
    {
        return [self::Pending, self::UnderReview];
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

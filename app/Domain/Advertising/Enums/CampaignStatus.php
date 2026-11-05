<?php

namespace App\Domain\Advertising\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case PendingReview = 'pending_review';
    case Active = 'active';
    case Paused = 'paused';
    case Exhausted = 'exhausted';
    case Completed = 'completed';
    case Rejected = 'rejected';
}

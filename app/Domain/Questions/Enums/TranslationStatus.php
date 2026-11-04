<?php

namespace App\Domain\Questions\Enums;

/**
 * Each language is approved on its own (spec §5.4). Only approved
 * translations of published questions are ever served to players.
 */
enum TranslationStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}

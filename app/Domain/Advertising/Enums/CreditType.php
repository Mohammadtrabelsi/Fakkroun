<?php

namespace App\Domain\Advertising\Enums;

/** The two consumption units of spec §18, billed and balanced separately. */
enum CreditType: string
{
    case Impression = 'impression';
    case SponsoredQuestion = 'sponsored_question';
}

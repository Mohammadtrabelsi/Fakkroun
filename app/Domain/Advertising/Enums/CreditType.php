<?php

namespace App\Domain\Advertising\Enums;

/**
 * Consumption units (spec §18, story 16 S12), billed and balanced
 * separately: credits of one type never pay for another.
 */
enum CreditType: string
{
    case Impression = 'impression';
    case SponsoredQuestion = 'sponsored_question';
    case SponsoredRound = 'sponsored_round';

    /** Platform setting holding the accounting price of one unit. */
    public function priceSetting(): string
    {
        return match ($this) {
            self::Impression => 'advertising.impression_price_minor',
            self::SponsoredQuestion => 'advertising.sponsored_question_price_minor',
            self::SponsoredRound => 'advertising.sponsored_round_price_minor',
        };
    }

    /** Package / invoice column holding how many units a pack includes. */
    public function packageColumn(): string
    {
        return match ($this) {
            self::Impression => 'impression_credits',
            self::SponsoredQuestion => 'sponsored_question_credits',
            self::SponsoredRound => 'sponsored_round_credits',
        };
    }
}

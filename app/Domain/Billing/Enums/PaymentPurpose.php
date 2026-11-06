<?php

namespace App\Domain\Billing\Enums;

enum PaymentPurpose: string
{
    case PlayerPlan = 'player_plan';
    case AdvertisingPack = 'advertising_pack';
}

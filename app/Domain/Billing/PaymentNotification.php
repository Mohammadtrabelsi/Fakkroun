<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Enums\PaymentStatus;

/** A verified payment update from a provider's webhook. */
final readonly class PaymentNotification
{
    public function __construct(
        public string $providerReference,
        public PaymentStatus $status,
        public int $amountMinor,
        public string $currency,
    ) {}
}

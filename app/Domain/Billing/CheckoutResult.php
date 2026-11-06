<?php

namespace App\Domain\Billing;

final readonly class CheckoutResult
{
    public function __construct(
        public ?string $redirectUrl = null,
        public ?string $providerReference = null,
        public ?string $instructions = null,
    ) {}
}

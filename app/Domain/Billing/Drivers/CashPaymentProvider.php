<?php

namespace App\Domain\Billing\Drivers;

use App\Domain\Billing\CheckoutResult;
use App\Domain\Billing\Contracts\PaymentProvider;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Cash only, until another payment method is configured (owner decision
 * 2026-11-05): no online checkout. The player pays in cash, and an
 * administrator records it (Admin → Paid accounts, or plans:grant), which
 * calls PaymentService::grantManually.
 */
class CashPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'cash';
    }

    public function supportsOnlineCheckout(): bool
    {
        return false;
    }

    public function startCheckout(Payment $payment, string $returnUrl): CheckoutResult
    {
        return new CheckoutResult(instructions: __('billing.cash_instructions'));
    }

    public function verifyWebhook(Request $request): ?PaymentNotification
    {
        return null;
    }

    public function refund(Payment $payment): void
    {
        // Cash refunds are handed back in person.
    }
}

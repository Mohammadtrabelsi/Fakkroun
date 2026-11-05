<?php

namespace App\Domain\Billing\Drivers;

use App\Domain\Billing\CheckoutResult;
use App\Domain\Billing\Contracts\PaymentProvider;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentNotification;
use Illuminate\Http\Request;

/**
 * Placeholder until a gateway is chosen: no online checkout. Payments are
 * received outside the platform and confirmed by an administrator
 * (PaymentService::markPaid), as advertising purchases are today.
 */
class ManualPaymentProvider implements PaymentProvider
{
    public function name(): string
    {
        return 'manual';
    }

    public function supportsOnlineCheckout(): bool
    {
        return false;
    }

    public function startCheckout(Payment $payment, string $returnUrl): CheckoutResult
    {
        return new CheckoutResult(instructions: __('billing.manual_instructions'));
    }

    public function verifyWebhook(Request $request): ?PaymentNotification
    {
        return null;
    }

    public function refund(Payment $payment): void
    {
        // Refunds are made outside the platform; PaymentService records them.
    }
}

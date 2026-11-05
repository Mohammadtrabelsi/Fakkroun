<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\CheckoutResult;
use App\Domain\Billing\Models\Payment;
use App\Domain\Billing\PaymentNotification;
use Illuminate\Http\Request;

/**
 * A payment gateway, kept abstract until one is chosen (spec §19, owner
 * decision 2026-11-05). Implement this interface in a new driver and select
 * it with FAKKROUN_PAYMENT_DRIVER; nothing else in the app needs to change.
 *
 * Rules every driver must follow:
 *  - Never mark a payment paid from the browser's word: only from
 *    verifyWebhook() (signature checked) or an admin confirmation.
 *  - providerReference must be unique per payment at that provider, so a
 *    repeated webhook is recognised and ignored.
 */
interface PaymentProvider
{
    /** Short stable name stored on each payment, e.g. "cash". */
    public function name(): string;

    /** Whether players can pay online with this driver. */
    public function supportsOnlineCheckout(): bool;

    /**
     * Starts paying $payment (already stored as pending). Returns where to
     * send the user, or instructions when there is no online checkout.
     */
    public function startCheckout(Payment $payment, string $returnUrl): CheckoutResult;

    /**
     * Turns an incoming webhook into a verified notification, or null when
     * the request is not authentic or not about a payment.
     */
    public function verifyWebhook(Request $request): ?PaymentNotification;

    /** Asks the provider to refund a paid payment. */
    public function refund(Payment $payment): void;
}

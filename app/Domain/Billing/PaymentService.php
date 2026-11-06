<?php

namespace App\Domain\Billing;

use App\Domain\Billing\Contracts\PaymentProvider;
use App\Domain\Billing\Enums\PaymentPurpose;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Models\Payment;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\DB;

/**
 * Provider-agnostic payment flow. Callers never talk to a gateway directly:
 * they create a pending Payment, hand it to the configured PaymentProvider,
 * and the payment becomes paid only through a verified notification or an
 * admin confirmation. Marking paid is idempotent.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentProvider $provider,
        private readonly PlanService $plans,
        private readonly PlatformSettings $settings,
    ) {}

    /** Monthly price of the paid account (Admin → Game settings → Accounts). */
    public function planPrice(): int
    {
        return (int) $this->settings->get('accounts.paid_plan_price_minor');
    }

    public function planCurrency(): string
    {
        return (string) $this->settings->get('accounts.paid_plan_currency');
    }

    public function provider(): PaymentProvider
    {
        return $this->provider;
    }

    /**
     * A pending payment for $months of the paid player plan.
     */
    public function startPlanPurchase(User $user, int $months, string $returnUrl): array
    {
        $price = $this->planPrice();

        if ($price <= 0) {
            throw new DomainActionException(__('billing.errors.plan_not_available'));
        }

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::PlayerPlan,
            'provider' => $this->provider->name(),
            'amount_minor' => $price * max(1, $months),
            'currency' => $this->planCurrency(),
            'metadata' => ['months' => max(1, $months)],
        ]);

        $result = $this->provider->startCheckout($payment, $returnUrl);

        if ($result->providerReference !== null) {
            $payment->forceFill(['provider_reference' => $result->providerReference])->save();
        }

        return [$payment, $result];
    }

    /**
     * Confirms a payment and applies what it pays for. Safe to call twice.
     */
    public function markPaid(Payment $payment, ?string $providerReference = null): Payment
    {
        return DB::transaction(function () use ($payment, $providerReference): Payment {
            /** @var Payment $locked */
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentStatus::Paid) {
                return $locked;
            }

            if ($locked->status !== PaymentStatus::Pending) {
                throw new DomainActionException(__('billing.errors.not_pending'));
            }

            $locked->forceFill([
                'status' => PaymentStatus::Paid,
                'paid_at' => now(),
                'provider_reference' => $locked->provider_reference ?? $providerReference,
            ])->save();

            if ($locked->purpose === PaymentPurpose::PlayerPlan && $locked->user) {
                $this->plans->extend($locked->user, (int) ($locked->metadata['months'] ?? 1));
            }

            return $locked;
        });
    }

    /**
     * Applies a verified webhook notification from the provider.
     */
    public function handle(PaymentNotification $notification): ?Payment
    {
        $payment = Payment::query()
            ->where('provider', $this->provider->name())
            ->where('provider_reference', $notification->providerReference)
            ->first();

        if ($payment === null || $payment->amount_minor !== $notification->amountMinor || $payment->currency !== $notification->currency) {
            return null;
        }

        if ($notification->status === PaymentStatus::Paid) {
            return $this->markPaid($payment);
        }

        if (in_array($notification->status, [PaymentStatus::Failed, PaymentStatus::Cancelled], true) && $payment->status === PaymentStatus::Pending) {
            $payment->forceFill(['status' => $notification->status])->save();
        }

        return $payment;
    }

    /**
     * Records a cash payment taken by an administrator and starts the plan.
     * $reference is the receipt number; it must be unique.
     */
    public function grantManually(User $user, int $months, string $reference, int $amountMinor = 0, ?User $admin = null): Payment
    {
        if (Payment::query()->where('provider', 'cash')->where('provider_reference', $reference)->exists()) {
            throw new DomainActionException(__('billing.errors.duplicate_reference'));
        }

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::PlayerPlan,
            'provider' => 'cash',
            'provider_reference' => $reference,
            'amount_minor' => max(0, $amountMinor),
            'currency' => $this->planCurrency(),
            'metadata' => ['months' => max(1, $months), 'recorded_by' => $admin?->id],
        ]);

        return $this->markPaid($payment);
    }
}

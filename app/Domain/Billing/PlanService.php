<?php

namespace App\Domain\Billing;

use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Player plans (owner decision 2026-11-05): a free account sees ads, a paid
 * account doesn't, and a room hosted by a paid account is ad-free for all.
 */
class PlanService
{
    public const FREE = 'free';

    public const PAID = 'paid';

    public function isPaid(?User $user): bool
    {
        return $user !== null
            && $user->plan === self::PAID
            && ($user->plan_expires_at === null || $user->plan_expires_at->isFuture());
    }

    /**
     * Adds $months to the paid plan, starting from its current end when it
     * is still running, otherwise from now.
     */
    public function extend(User $user, int $months): User
    {
        $from = $this->isPaid($user) && $user->plan_expires_at ? $user->plan_expires_at : Carbon::now();

        $user->forceFill(['plan' => self::PAID, 'plan_expires_at' => $from->copy()->addMonths(max(1, $months))])->save();

        return $user;
    }

    public function cancel(User $user): User
    {
        $user->forceFill(['plan' => self::FREE, 'plan_expires_at' => null])->save();

        return $user;
    }
}

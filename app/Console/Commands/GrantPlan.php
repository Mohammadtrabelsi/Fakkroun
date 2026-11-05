<?php

namespace App\Console\Commands;

use App\Domain\Billing\PaymentService;
use App\Models\User;
use Illuminate\Console\Command;

/** Grants the paid (ad-free) plan for a payment received outside the platform. */
class GrantPlan extends Command
{
    protected $signature = 'plans:grant {email} {reference : Unique payment reference} {--months=1} {--amount=0 : Amount received, in minor units}';

    protected $description = 'Give a user the paid, ad-free account after a cash payment';

    public function handle(PaymentService $payments): int
    {
        $user = User::query()->where('email', $this->argument('email'))->first();

        if ($user === null) {
            $this->error('No user with that e-mail.');

            return self::FAILURE;
        }

        try {
            $payments->grantManually($user, (int) $this->option('months'), (string) $this->argument('reference'), (int) $this->option('amount'));
        } catch (\DomainException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Paid plan active until {$user->fresh()->plan_expires_at?->toDateString()}.");

        return self::SUCCESS;
    }
}

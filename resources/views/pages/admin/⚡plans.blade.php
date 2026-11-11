<?php

use App\Domain\Billing\PaymentService;
use App\Domain\Billing\PlanService;
use App\Models\User;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Paid (ad-free) user accounts, paid in cash for now. Advertiser packs are separate. */
new #[Layout('layouts::app')] #[Title('Paid accounts')] class extends Component
{
    use InteractsWithToasts;

    public string $email = '';

    public int $months = 1;

    public string $receipt = '';

    public ?int $amount = null;

    public function mount(): void
    {
        $this->authorize('manage platform settings');
    }

    #[Computed]
    public function paidUsers()
    {
        return app(\App\Services\UserService::class)->query()->where('plan', PlanService::PAID)->orderByDesc('plan_expires_at')->limit(200)->get(['id', 'name', 'email', 'plan', 'plan_expires_at']);
    }

    public function record(PaymentService $payments): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'months' => ['required', 'integer', 'min:1', 'max:36'],
            'receipt' => ['required', 'string', 'max:120'],
            'amount' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        $user = app(\App\Services\UserService::class)->query()->where('email', $this->email)->first();

        if ($user === null) {
            $this->addError('email', __('billing.plans.unknown_user'));

            return;
        }

        try {
            $payments->grantManually($user, $this->months, trim($this->receipt), $this->amount ?? $payments->planPrice() * $this->months, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('receipt', $e->getMessage());

            return;
        }

        $this->toast(__('billing.plans.recorded', ['date' => $user->fresh()->plan_expires_at?->isoFormat('ll')]));
        $this->reset(['email', 'months', 'receipt', 'amount']);
        unset($this->paidUsers);
    }

    public function cancel(int $id, PlanService $plans): void
    {
        $plans->cancel(app(\App\Services\UserService::class)->query()->findOrFail($id));
        $this->toast(__('billing.plans.cancelled'));
        unset($this->paidUsers);
    }
};
?>

@php($payments = app(\App\Domain\Billing\PaymentService::class))
<div class="ms-mw-720">
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('billing.plans.title') }}</h2>
            <p class="text-muted ms-note">{{ __('billing.plans.description') }}</p>
        </div>
    </div>

    @if ($payments->planPrice() > 0)
        <p class="tag tag-accent ms-notice">{{ __('billing.plans.price', ['price' => number_format($payments->planPrice() / 100, 2).' '.$payments->planCurrency()]) }}</p>
    @else
        <p class="tag tag-outline ms-notice"><a href="{{ route('admin.game-settings') }}" wire:navigate>{{ __('billing.plans.not_on_sale') }}</a></p>
    @endif

    <form wire:submit="record" class="card ms-stack-16 ms-mb-24">
        <h3 class="ms-fs-18">{{ __('billing.plans.record') }}</h3>
        <x-text-input wire:model="email" type="email" :placeholder="__('billing.plans.email')" :error="$errors->first('email')" />
        <x-input-error :message="$errors->first('email')" />
        <div class="ms-row-10 ms-wrap">
            <label class="ms-fs-13">{{ __('billing.plans.months') }} <input type="number" min="1" max="36" wire:model="months" class="input"></label>
            <label class="ms-fs-13">{{ __('billing.plans.amount') }} <input type="number" min="0" wire:model="amount" class="input" placeholder="{{ $payments->planPrice() * max(1, $months) }}"></label>
        </div>
        <x-text-input wire:model="receipt" type="text" :placeholder="__('billing.plans.receipt')" :error="$errors->first('receipt')" />
        <x-input-error :message="$errors->first('receipt')" />
        <div><button type="submit" class="btn btn-primary">{{ __('billing.plans.record') }}</button></div>
    </form>

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->paidUsers as $user)
                        <div class="row-card" wire:key="pu-{{ $user->id }}">
                            <div class="row-card-title"><div class="ms-fw-500">{{ $user->name }}</div><div class="text-muted ms-fs-12">{{ $user->email }}</div></div>
                            <div @class(['row-card-field', 'text-muted' => ! $user->hasPaidPlan()]) data-label="{{ __('billing.plans.until') }}">{{ $user->plan_expires_at?->isoFormat('ll') ?? '—' }}</div>
                            <div class="row-card-field ms-text-right"><button type="button" wire:click="cancel({{ $user->id }})" wire:confirm="{{ __('billing.plans.cancel') }}?" class="btn ms-btn-sm">{{ __('billing.plans.cancel') }}</button></div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('billing.plans.none') }}</div>
                    @endforelse
            </div>
        </div>
    </div>
</div>

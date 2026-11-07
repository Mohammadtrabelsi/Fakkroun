<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/*
 * Optional email verification (front office). Playing never waits on it:
 * players who signed up with a phone number, or who already verified, are
 * sent straight on. Verifying just confirms the address (e.g. for password
 * reset mails).
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Verify email')] class extends Component
{
    public function mount(): void
    {
        $user = auth()->user();

        if (blank($user->email) || $user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function sendVerification(): void
    {
        $user = auth()->user();

        if (blank($user->email) || $user->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        $user->sendEmailVerificationNotification();

        session()->flash('status', 'verification-link-sent');
    }
};
?>

<div class="fk-container flex justify-center pt-12 pb-20">
    <div class="flex w-full max-w-[480px] flex-col gap-6 rounded-[28px] border border-sand-300 bg-paper p-[clamp(20px,4vw,40px)]">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[40px] leading-none">{{ __('verify-email.verify_your_email') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('verify-email.optional_lead') }}</p>
        </div>

        <p class="m-0 rounded-xl border border-sand-300 bg-white p-4 text-ink">
            {{ __('verify-email.send_link_to') }}
            <strong class="break-all">{{ auth()->user()->email }}</strong>
        </p>

        @if (session('status') === 'verification-link-sent')
            <p role="status" class="m-0 rounded-xl bg-tint-green p-3 font-semibold text-[#0B5A55]">
                {{ __('verify-email.verification_link_sent') }}
            </p>
        @endif

        <div class="flex flex-col gap-3">
            <a href="{{ route('rooms.create') }}" class="fk-btn fk-btn-primary min-h-14 rounded-2xl text-lg">
                {{ __('verify-email.continue_to_play') }}
            </a>

            <button type="button" wire:click="sendVerification" wire:loading.attr="disabled" wire:target="sendVerification"
                    class="fk-btn fk-btn-outline min-h-12 rounded-2xl">
                {{ __('verify-email.send_verification_email') }}
            </button>
        </div>

        <div class="m-0 border-t border-sand-300 pt-4 text-sm text-ink-soft">
            <livewire:logout variant="link" :label="__('verify-email.log_out')" />
        </div>
    </div>
</div>

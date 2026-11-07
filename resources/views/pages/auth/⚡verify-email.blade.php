<?php

use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Verify email')] class extends Component
{
    public function mount(): void
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: true);
        }
    }

    public function sendVerification(): void
    {
        if (auth()->user()->hasVerifiedEmail()) {
            $this->redirect(route('dashboard'), navigate: true);

            return;
        }

        auth()->user()->sendEmailVerificationNotification();

        session()->flash('status', 'verification-link-sent');
    }
};
?>

<div>
    <h1>{{ __('verify-email.verify_your_email') }}</h1>
    <p class="text-muted ms-mb-24">
        {{ __('verify-email.thanks_for_signing_up') }} {{ __('verify-email.before_getting_started') }} <strong>{{ auth()->user()->email }}</strong>. {{ __('verify-email.did_not_receive') }} {{ __('verify-email.will_gladly_send') }}
    </p>

    @if (session('status') === 'verification-link-sent')
        <div class="tag tag-accent ms-notice">
            {{ __('verify-email.verification_link_sent') }}
        </div>
    @endif

    <div class="ms-row-12-center-wrap">
        <button
            type="button"
            wire:click="sendVerification"
            wire:loading.attr="disabled"
            wire:target="sendVerification"
            class="btn btn-primary"
        >
            {{ __('verify-email.resend_verification_email') }}
        </button>

        <livewire:logout :label="__('verify-email.log_out')" />
    </div>
</div>

<?php

use Illuminate\Support\Facades\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

new #[Layout('layouts::guest')] #[Title('Forgot password')] class extends Component
{
    use UsesSpamProtection;

    /** Honeypot + timing fields (spatie/laravel-honeypot); bots that fill them get a 403. */
    public HoneypotData $extraFields;

    public function mount(): void
    {
        $this->extraFields = new HoneypotData;
    }

    #[Validate('required|string|email')]
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->protectAgainstSpam();

        $this->validate();

        $result = Password::sendResetLink(['email' => $this->email]);

        if ($result === Password::RESET_LINK_SENT) {
            $this->status = __($result);
            $this->reset('email');

            return;
        }

        $this->addError('email', __($result));
    }
};
?>

<div>
    <h1>{{ __('forgot-password.forgot_your_password') }}</h1>
    <p class="text-muted ms-mb-28">{{ __('forgot-password.no_worries') }} {{ __('forgot-password.we_ll_send_you_a_link') }}.</p>

    @if ($status)
        <div class="tag tag-accent ms-notice-20">{{ $status }}</div>
    @endif

    <form wire:submit="sendResetLink">
        <x-honeypot livewire-model="extraFields" />
        <div class="field ms-mb-22">
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                autofocus
                autocomplete="username"
                placeholder="{{ __('forgot-password.email_placeholder') }}"
                :error="$errors->first('email')"
            />
            <x-input-error :message="$errors->first('email')" />
        </div>

        <x-primary-button wire:loading.attr="disabled" wire:target="sendResetLink">
            <svg wire:loading wire:target="sendResetLink" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            {{ __('forgot-password.email_password_reset_link') }}
        </x-primary-button>
    </form>

    <p class="text-muted ms-form-foot">
        {{ __('forgot-password.remembered_your_password') }}
        <a href="{{ route('login') }}">{{ __('forgot-password.back_to_sign_in') }}</a>
    </p>
</div>

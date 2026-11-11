<?php

use App\Models\User;
use App\Support\Auth\PostLoginRedirect;
use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Two-factor authentication')] class extends Component
{
    public string $code = '';

    public bool $useRecovery = false;

    public function mount(): void
    {
        if (! session()->has(TwoFactorService::CHALLENGE_SESSION_KEY)) {
            $this->redirect(route('login'));
        }
    }

    public function verify(TwoFactorService $twoFactor): void
    {
        $this->validate(['code' => ['required', 'string', 'max:64']]);

        $pending = session(TwoFactorService::CHALLENGE_SESSION_KEY);
        $user = $pending ? app(\App\Services\UserService::class)->query()->find($pending['id']) : null;

        if ($user === null) {
            $this->redirect(route('login'));

            return;
        }

        $throttleKey = '2fa|'.$user->id.'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'code' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        if (! $twoFactor->verifyChallenge($user, $this->code)) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'code' => __('two_factor.invalid_code'),
            ]);
        }

        RateLimiter::clear($throttleKey);
        session()->forget(TwoFactorService::CHALLENGE_SESSION_KEY);

        Auth::login($user, $pending['remember'] ?? false);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(PostLoginRedirect::urlFor($user));
    }

    public function toggleRecovery(): void
    {
        $this->useRecovery = ! $this->useRecovery;
        $this->reset('code');
        $this->resetValidation();
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    <h1>{{ __('two_factor.challenge_title') }}</h1>
    <p class="text-muted ms-mb-28">{{ $useRecovery ? __('two_factor.recovery_description') : __('two_factor.challenge_description') }}</p>

    <form wire:submit="verify">
        <div class="field">
            <x-input-label for="code" :value="$useRecovery ? __('two_factor.recovery_label') : __('two_factor.code_label')" />
            <x-text-input
                wire:model="code"
                id="code"
                type="text"
                :inputmode="$useRecovery ? 'text' : 'numeric'"
                autocomplete="one-time-code"
                autofocus
                :error="$errors->first('code')"
            />
            <x-input-error :message="$errors->first('code')" />
        </div>

        <x-primary-button wire:loading.attr="disabled" wire:target="verify">
            {{ __('two_factor.verify') }}
        </x-primary-button>
    </form>

    <p class="text-muted ms-form-foot">
        <button type="button" wire:click="toggleRecovery" class="ms-fs-12 ms-textbtn">
            {{ $useRecovery ? __('two_factor.use_code') : __('two_factor.use_recovery') }}
        </button>
    </p>
</div>

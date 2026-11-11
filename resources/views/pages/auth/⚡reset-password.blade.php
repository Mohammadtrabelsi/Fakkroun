<?php

use App\Services\UserService;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::guest')] #[Title('Reset password')] class extends Component
{
    use \App\Support\Livewire\ThrottlesSubmissions;

    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ];
    }

    public function mount(string $token): void
    {
        $this->token = $token;
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->throttleSubmission('reset-password', 'email');

        $this->validate();

        $status = Password::reset(
            [
                'token' => $this->token,
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
            ],
            function ($user) {
                app(UserService::class)->resetPassword($user, $this->password);

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            $this->addError('email', __($status));

            return;
        }

        session()->flash('status', __($status));
        $this->redirect(route('login'));
    }
};
?>

<div>
    <h1>{{ __('actions.reset_heading') }}</h1>
    <p class="text-muted ms-mb-28">{{ __('actions.reset_lead') }}</p>

    <form wire:submit="resetPassword">
        <div class="field">
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                autofocus
                autocomplete="username"
                :error="$errors->first('email')"
            />
            <x-input-error :message="$errors->first('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" value="New password" />
            <x-text-input
                wire:model="password"
                id="password"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
                :error="$errors->first('password')"
            />
            <x-input-error :message="$errors->first('password')" />
        </div>

        <div class="field ms-mb-22">
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
            />
        </div>

        <x-primary-button wire:loading.attr="disabled" wire:target="resetPassword">
            <svg wire:loading wire:target="resetPassword" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            {{ __('reset-password.reset_password') }}
        </x-primary-button>
    </form>
</div>

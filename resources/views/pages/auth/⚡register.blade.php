<?php

use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

new #[Layout('layouts::guest')] #[Title('Create account')] class extends Component
{
    use UsesSpamProtection;

    /** Honeypot + timing fields (spatie/laravel-honeypot); bots that fill them get a 403. */
    public HoneypotData $extraFields;

    public function mount(): void
    {
        $this->extraFields = new HoneypotData;
    }

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    public function register(): void
    {
        $this->protectAgainstSpam();

        $validated = $this->validate();

        $user = app(UserService::class)->register($validated);

        Auth::login($user);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        $this->redirect(route('dashboard'), navigate: true);
    }
};
?>

<div>
    <h1>{{ __('register.create_account') }}</h1>
    <p class="text-muted ms-mb-28">{{ __('register.start_building') }} {{ config('app.name') }} {{ __('register.today') }}.</p>

    <form wire:submit="register">
        <x-honeypot livewire-model="extraFields" />
        <div class="field">
            <x-input-label for="name" value="Full name" />
            <x-text-input
                wire:model="name"
                id="name"
                type="text"
                autofocus
                autocomplete="name"
                placeholder="{{ __('register.name_placeholder') }}"
                :error="$errors->first('name')"
            />
            <x-input-error :message="$errors->first('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" value="Email" />
            <x-text-input
                wire:model="email"
                id="email"
                type="email"
                autocomplete="username"
                placeholder="{{ __('register.email_placeholder') }}"
                :error="$errors->first('email')"
            />
            <x-input-error :message="$errors->first('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" value="Password" />
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
            <x-input-label for="password_confirmation" value="Confirm password" />
            <x-text-input
                wire:model="password_confirmation"
                id="password_confirmation"
                type="password"
                autocomplete="new-password"
                placeholder="••••••••"
            />
        </div>

        <x-primary-button wire:loading.attr="disabled" wire:target="register">
            <svg wire:loading wire:target="register" class="h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"/></svg>
            Create account
        </x-primary-button>
    </form>

    <p class="text-muted ms-form-foot">
        {{ __('register.already_have_account') }}
        <a href="{{ route('login') }}" wire:navigate>{{ __('register.sign_in') }}</a>
    </p>
</div>

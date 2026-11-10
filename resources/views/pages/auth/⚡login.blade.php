<?php

use App\Support\Auth\AuthenticatesIntoPortal;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Validate;
use Livewire\Component;

/*
 * Player sign-in (front office). Admins, question writers and reviewers use
 * the separate staff portal at /admin/login (pages::auth.admin-login).
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Log in')] class extends Component
{
    use AuthenticatesIntoPortal;

    /** Email address or phone number (players can sign up with either). */
    #[Validate('required|string|max:255')]
    public string $email = '';

    #[Validate('required|string')]
    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->attemptPortalLogin(self::PORTAL_PLAYER);
    }
};
?>

<div class="fk-container flex justify-center pt-12 pb-20">
    <div class="flex w-full max-w-[480px] flex-col gap-6 rounded-[28px] border border-sand-300 bg-paper p-[clamp(20px,4vw,40px)]">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[40px] leading-none">{{ __('login.player_title') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('login.player_lead') }}</p>
        </div>

        @if (session('status'))
            <p role="status" class="m-0 rounded-xl bg-tint-green p-3 font-semibold text-[#0B5A55]">{{ session('status') }}</p>
        @endif

        <form wire:submit="login" class="flex flex-col gap-5">
            <div class="flex flex-col gap-2">
                <label for="email" class="font-semibold">{{ __('login.email_or_phone') }}</label>
                <input wire:model="email" id="email" type="text" inputmode="email" autofocus autocomplete="username"
                       placeholder="{{ __('login.email_or_phone_placeholder') }}"
                       @error('email') aria-invalid="true" aria-describedby="email-error" @enderror
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                @error('email')
                    <span id="email-error" role="alert" class="text-sm text-wrong">
                        {{ $message }}
                        @if ($message === __('login.use_staff_portal'))
                            <a href="{{ route('admin.login') }}" class="font-semibold">{{ __('login.go_to_staff_portal') }}</a>
                        @endif
                    </span>
                @enderror
            </div>

            <div class="flex flex-col gap-2">
                <div class="flex items-center justify-between gap-3">
                    <label for="password" class="font-semibold">{{ __('login.password') }}</label>
                    <a href="{{ route('password.request') }}" class="text-sm font-semibold text-sea">{{ __('login.forgot_password') }}</a>
                </div>
                <input wire:model="password" id="password" type="password" autocomplete="current-password" placeholder="••••••••"
                       @error('password') aria-invalid="true" @enderror
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                @error('password') <span role="alert" class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <label class="flex min-h-11 items-center gap-2.5">
                <input wire:model="remember" type="checkbox" class="h-5 w-5 accent-[#0B6E69]"> {{ __('login.remember_me') }}
            </label>

            <button type="submit" wire:loading.attr="disabled" wire:target="login" class="fk-btn fk-btn-primary min-h-14 rounded-2xl text-lg">
                {{ __('login.sign_in') }}
            </button>
        </form>

        <p class="m-0 text-ink-muted">
            {{ __('login.no_account') }}
            <a href="{{ route('register') }}" class="font-semibold text-sea">{{ __('login.create_one') }}</a>
        </p>

        <p class="m-0 border-t border-sand-300 pt-4 text-sm text-ink-soft">
            {{ __('login.staff_hint') }}
            <a href="{{ route('admin.login') }}" class="font-semibold">{{ __('login.go_to_staff_portal') }}</a>
        </p>
    </div>
</div>

<?php

use App\Models\User;
use App\Services\UserService;
use App\Support\Auth\LoginIdentifier;
use App\Support\Auth\PostLoginRedirect;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Honeypot\Http\Livewire\Concerns\HoneypotData;
use Spatie\Honeypot\Http\Livewire\Concerns\UsesSpamProtection;

/*
 * Player sign-up (front office). A player gives an email address OR a phone
 * number and can play right away: there is no email verification step.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Create account')] class extends Component
{
    use UsesSpamProtection;

    /** Honeypot + timing fields (spatie/laravel-honeypot); bots that fill them get a 403. */
    public HoneypotData $extraFields;

    public function mount(): void
    {
        $this->extraFields = new HoneypotData;
    }

    public string $name = '';

    /** Email address or phone number. */
    public string $login = '';

    public string $password = '';

    public string $password_confirmation = '';

    protected function rules(): array
    {
        $login = LoginIdentifier::isEmail($this->login)
            ? ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')]
            : ['required', 'string', 'max:32',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! LoginIdentifier::isValidPhone((string) $value)) {
                        $fail(__('register.invalid_login'));
                    }
                },
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::withTrashed()->where('phone', LoginIdentifier::normalizePhone((string) $value))->exists()) {
                        $fail(__('register.phone_taken'));
                    }
                },
            ];

        return [
            'name' => ['required', 'string', 'max:255'],
            'login' => $login,
            'password' => ['required', 'confirmed', Password::defaults()],
        ];
    }

    protected function validationAttributes(): array
    {
        return ['login' => __('register.email_or_phone')];
    }

    public function register(): void
    {
        $this->protectAgainstSpam();

        $validated = $this->validate();

        $column = LoginIdentifier::column($validated['login']);

        $user = app(UserService::class)->register([
            'name' => $validated['name'],
            $column => $validated['login'],
            'password' => $validated['password'],
        ]);

        Auth::login($user);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        // Straight to the game (or the invitation that sent them here).
        $this->redirect(PostLoginRedirect::urlFor($user));
    }
};
?>

<div class="fk-container flex justify-center pt-12 pb-20">
    <div class="flex w-full max-w-[480px] flex-col gap-6 rounded-[28px] border border-sand-300 bg-paper p-[clamp(20px,4vw,40px)]">
        <div class="flex flex-col gap-2">
            <h1 class="fk-h2 m-0 text-[40px] leading-none">{{ __('register.create_account') }}</h1>
            <p class="m-0 text-lg text-ink-muted">{{ __('register.player_lead') }}</p>
        </div>

        <form wire:submit="register" class="flex flex-col gap-5">
            <x-honeypot livewire-model="extraFields" />

            <div class="flex flex-col gap-2">
                <label for="name" class="font-semibold">{{ __('register.name') }}</label>
                <input wire:model="name" id="name" type="text" autofocus autocomplete="name"
                       placeholder="{{ __('register.name_placeholder') }}"
                       @error('name') aria-invalid="true" aria-describedby="name-error" @enderror
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                @error('name') <span id="name-error" role="alert" class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="login" class="font-semibold">{{ __('register.email_or_phone') }}</label>
                <input wire:model="login" id="login" type="text" inputmode="email" autocomplete="username"
                       placeholder="{{ __('register.email_or_phone_placeholder') }}"
                       aria-describedby="login-hint @error('login') login-error @enderror"
                       @error('login') aria-invalid="true" @enderror
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                <span id="login-hint" class="text-sm text-ink-soft">{{ __('register.email_or_phone_hint') }}</span>
                @error('login') <span id="login-error" role="alert" class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="password" class="font-semibold">{{ __('register.password') }}</label>
                <input wire:model="password" id="password" type="password" autocomplete="new-password" placeholder="••••••••"
                       @error('password') aria-invalid="true" aria-describedby="password-error" @enderror
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
                @error('password') <span id="password-error" role="alert" class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="password_confirmation" class="font-semibold">{{ __('register.confirm_password') }}</label>
                <input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" placeholder="••••••••"
                       class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4 text-[17px] font-medium text-ink">
            </div>

            <button type="submit" wire:loading.attr="disabled" wire:target="register" class="fk-btn fk-btn-primary min-h-14 rounded-2xl text-lg">
                {{ __('register.create_and_play') }}
            </button>
        </form>

        <p class="m-0 text-ink-muted">
            {{ __('register.already_have_account') }}
            <a href="{{ route('login') }}" class="font-semibold text-sea">{{ __('register.sign_in') }}</a>
        </p>
    </div>
</div>

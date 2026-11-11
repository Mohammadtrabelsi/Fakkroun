<?php

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Two-factor authentication')] class extends Component
{
    /** Secret awaiting confirmation (not yet enforced at sign-in). */
    public string $pendingSecret = '';

    public string $confirmCode = '';

    /** Plain recovery codes, shown once right after they are generated. */
    public array $recoveryCodes = [];

    public string $password = '';

    public function mount(): void
    {
        $user = auth()->user();

        if (! $user->hasTwoFactorEnabled() && $user->two_factor_secret) {
            $this->pendingSecret = $user->two_factor_secret;
        }
    }

    public function startSetup(TwoFactorService $twoFactor): void
    {
        $this->pendingSecret = $twoFactor->beginSetup(auth()->user());
        $this->reset('confirmCode', 'recoveryCodes');
    }

    public function confirmSetup(TwoFactorService $twoFactor): void
    {
        $this->validate(['confirmCode' => ['required', 'string', 'size:6']]);

        $codes = $twoFactor->confirm(auth()->user(), $this->confirmCode);

        if ($codes === null) {
            $this->addError('confirmCode', __('two_factor.setup_invalid'));

            return;
        }

        $this->recoveryCodes = $codes;
        $this->reset('pendingSecret', 'confirmCode');
        session()->flash('status', __('two_factor.saved'));
    }

    public function regenerateCodes(TwoFactorService $twoFactor): void
    {
        $this->recoveryCodes = $twoFactor->regenerateRecoveryCodes(auth()->user());
        session()->flash('status', __('two_factor.regenerated'));
    }

    public function disable(TwoFactorService $twoFactor): void
    {
        $this->validate(['password' => ['required', 'string']]);

        $user = auth()->user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', __('two_factor.password_invalid'));

            return;
        }

        $twoFactor->disable($user);

        $this->reset('password', 'pendingSecret', 'recoveryCodes');
        session()->flash('status', __('two_factor.disabled_notice'));
    }

    public function render()
    {
        $user = auth()->user();

        return $this->view([
            'enabled' => $user->hasTwoFactorEnabled(),
            'remainingCodes' => count($user->two_factor_recovery_codes ?? []),
            'otpauthUri' => $this->pendingSecret !== ''
                ? app(TwoFactorService::class)->provisioningUri($user, $this->pendingSecret)
                : null,
        ]);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <a href="{{ route('profile.edit') }}" wire:navigate class="text-muted ms-fs-13">&larr; {{ __('profile.profile') }}</a>
        <h2 class="ms-mt-8">{{ __('two_factor.settings_title') }}</h2>
        <p class="text-muted ms-note">{{ __('two_factor.settings_description') }}</p>
    </div>

    @if (session('status'))
        <div data-autohide="5000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    {{-- Recovery codes are shown once, straight after they are generated. --}}
    @if (! empty($recoveryCodes))
        <div class="card ms-stack-16 ms-mb-16">
            <h3 class="ms-fs-18">{{ __('two_factor.recovery_codes_title') }}</h3>
            <p class="text-muted ms-note">{{ __('two_factor.recovery_codes_hint') }}</p>
            <div class="ms-code-grid">
                @foreach ($recoveryCodes as $code)
                    <div class="ms-fs-13">{{ $code }}</div>
                @endforeach
            </div>
            <div>
                <button type="button" wire:click="$set('recoveryCodes', [])" class="btn">{{ __('two_factor.done') }}</button>
            </div>
        </div>
    @endif

    @if ($enabled)
        <div class="card ms-stack-16 ms-mb-16">
            <div class="ms-row-between">
                <h3 class="ms-fs-18">{{ __('two_factor.settings_title') }}</h3>
                <span class="tag tag-accent">{{ __('two_factor.enabled') }}</span>
            </div>
            <p class="text-muted ms-note">{{ __('two_factor.recovery_codes_remaining', ['count' => $remainingCodes]) }}</p>
            <div>
                <button type="button" wire:click="regenerateCodes" wire:confirm="{{ __('two_factor.regenerate') }}?" class="btn">{{ __('two_factor.regenerate') }}</button>
            </div>
        </div>

        <form wire:submit="disable" class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ __('two_factor.disable_title') }}</h3>
            <p class="text-muted ms-note">{{ __('two_factor.disable_description') }}</p>
            <div class="field ms-mb-0">
                <x-input-label for="password" :value="__('two_factor.password')" />
                <x-text-input wire:model="password" id="password" type="password" autocomplete="current-password" :error="$errors->first('password')" />
                <x-input-error :message="$errors->first('password')" />
            </div>
            <div>
                <button type="submit" class="btn ms-btn-sm-danger" wire:loading.attr="disabled" wire:target="disable">{{ __('two_factor.disable') }}</button>
            </div>
        </form>
    @elseif ($pendingSecret !== '')
        <form wire:submit="confirmSetup" class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ __('two_factor.setup_title') }}</h3>
            <p class="text-muted ms-note">{{ __('two_factor.setup_description') }}</p>

            <div class="field ms-mb-0">
                <x-input-label :value="__('two_factor.secret_label')" />
                <input type="text" readonly value="{{ $pendingSecret }}" class="input ms-mono" x-data x-on:click="$el.select()">
                <p class="text-muted ms-fs-12 ms-mt-8 ms-break-all">{{ $otpauthUri }}</p>
            </div>

            <div class="field ms-mb-0">
                <x-input-label for="confirmCode" :value="__('two_factor.confirm_label')" />
                <x-text-input wire:model="confirmCode" id="confirmCode" type="text" inputmode="numeric" autocomplete="one-time-code" :error="$errors->first('confirmCode')" />
                <x-input-error :message="$errors->first('confirmCode')" />
            </div>

            <div class="ms-row-10">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="confirmSetup">{{ __('two_factor.confirm') }}</button>
                <button type="button" wire:click="$set('pendingSecret', '')" class="btn">{{ __('two_factor.cancel') }}</button>
            </div>
        </form>
    @else
        <div class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ __('two_factor.settings_title') }}</h3>
            <div class="ms-row-between">
                <span class="tag tag-neutral">{{ __('two_factor.disabled') }}</span>
                <button type="button" wire:click="startSetup" class="btn btn-primary">{{ __('two_factor.enable') }}</button>
            </div>
        </div>
    @endif
</div>

<?php

use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('Profile')] class extends Component
{
    use WithFileUploads;

    public string $name = '';

    public string $locale = '';

    public string $question_locale = '';

    /** @var \Illuminate\Http\UploadedFile|null */
    public $avatar = null;

    public string $email = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $delete_password = '';

    public function mount(): void
    {
        $this->name = auth()->user()->name;
        $this->email = auth()->user()->email;
        $this->locale = (string) auth()->user()->locale;
        $this->question_locale = (string) auth()->user()->question_locale;
    }

    public function updatePreferences(UserService $users): void
    {
        $locales = array_keys(config('app.available_locales', []));

        $validated = $this->validate([
            'locale' => ['nullable', Rule::in($locales)],
            'question_locale' => ['nullable', Rule::in($locales)],
        ]);

        $users->updatePreferences(auth()->user(), $validated);

        // Reload so the new interface language applies to the whole page.
        $this->redirect(route('profile.edit'), navigate: true);
    }

    public function updatedAvatar(): void
    {
        $this->validate([
            'avatar' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:1024', 'dimensions:max_width=4000,max_height=4000'],
        ]);

        app(UserService::class)->updateAvatar(auth()->user(), $this->avatar);

        $this->reset('avatar');

        $this->dispatch('avatar-updated');
    }

    public function removeAvatar(UserService $users): void
    {
        $users->removeAvatar(auth()->user());

        $this->dispatch('avatar-updated');
    }

    public function updateProfile(UserService $users): void
    {
        $user = auth()->user();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        $users->updateProfile($user, $validated);

        $this->dispatch('profile-updated');
    }

    public function updatePassword(UserService $users): void
    {
        $validated = $this->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $users->updatePassword(auth()->user(), $validated['password']);

        $this->reset('current_password', 'password', 'password_confirmation');

        $this->dispatch('password-updated');
    }

    public function resendVerification(): void
    {
        auth()->user()->sendEmailVerificationNotification();

        $this->dispatch('verification-sent');
    }

    public function deleteAccount(UserService $users): void
    {
        $this->validate([
            'delete_password' => ['required', 'string', 'current_password'],
        ]);

        $users->deleteOwnAccount(auth()->user());

        $this->redirect('/', navigate: true);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('profile.profile') }}</h2>
        <p class="text-muted ms-note">{{ __('profile.manage_account_info') }}</p>
    </div>

    {{-- Profile picture --}}
    <div class="card ms-stack-16">
        <div>
            <h3 class="ms-fs-18">{{ __('account.avatar') }}</h3>
            <p class="text-muted ms-note">{{ __('account.avatar_description') }}</p>
        </div>

        <div data-show-on="avatar-updated" class="tag tag-accent">
            <span class="ms-block-pad">{{ __('account.avatar_updated') }}</span>
        </div>

        <div class="ms-row-between">
            <x-user-avatar :user="auth()->user()" size="56" />

            <div class="ms-row-12-rel">
                <label for="avatar" class="btn">{{ __('account.upload_avatar') }}</label>
                <input type="file" wire:model="avatar" id="avatar" accept="image/jpeg,image/png,image/webp" class="sr-only">

                @if (auth()->user()->avatar_path)
                    <button type="button" wire:click="removeAvatar" class="btn ms-outline-danger">{{ __('account.remove_avatar') }}</button>
                @endif
            </div>
        </div>
        <div wire:loading wire:target="avatar" class="text-muted ms-fs-12">Uploading…</div>
        <x-input-error :message="$errors->first('avatar')" />
    </div>

    {{-- Language preferences --}}
    <form wire:submit="updatePreferences" class="card ms-mt16-stack-16">
        <div>
            <h3 class="ms-fs-18">{{ __('account.preferences') }}</h3>
            <p class="text-muted ms-note">{{ __('account.preferences_description') }}</p>
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="locale" :value="__('account.interface_language')" />
            <select wire:model="locale" id="locale" class="input">
                <option value="">{{ __('account.platform_default') }}</option>
                @foreach (config('app.available_locales') as $code => $meta)
                    <option value="{{ $code }}" lang="{{ $code }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <x-input-error :message="$errors->first('locale')" />
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="question_locale" :value="__('account.question_language')" />
            <select wire:model="question_locale" id="question_locale" class="input">
                <option value="">{{ __('account.same_as_interface') }}</option>
                @foreach (config('app.available_locales') as $code => $meta)
                    <option value="{{ $code }}" lang="{{ $code }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <x-input-error :message="$errors->first('question_locale')" />
        </div>

        <div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updatePreferences">Save</button>
        </div>
    </form>

    {{-- Profile information --}}
    <form wire:submit="updateProfile" class="card ms-mt16-stack-16">
        <div>
            <h3 class="ms-fs-18">{{ __('profile.account_information') }}</h3>
            <p class="text-muted ms-note">{{ __('profile.update_name_email') }}</p>
        </div>

        <div data-show-on="profile-updated" class="tag tag-accent">
            <span class="ms-block-pad">{{ __('profile.profile_updated') }}</span>
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="name" value="Full name" />
            <x-text-input wire:model="name" id="name" type="text" autocomplete="name" :error="$errors->first('name')" />
            <x-input-error :message="$errors->first('name')" />
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="email" value="Email" />
            <x-text-input wire:model="email" id="email" type="email" autocomplete="username" :error="$errors->first('email')" />
            <x-input-error :message="$errors->first('email')" />

            @if (! auth()->user()->hasVerifiedEmail())
                <p class="text-muted ms-fs12-mt8">
                    {{ __('profile.unverified_email') }}
                    <button type="button" wire:click="resendVerification" class="btn-ghost ms-linkbtn">
                        {{ __('profile.resend_verification') }}
                    </button>
                    <span
                        data-show-on="verification-sent"
                        class="ms-hidden-danger"
                    >— a new link has been sent.</span>
                </p>
            @endif
        </div>

        <div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updateProfile">Save</button>
        </div>
    </form>

    {{-- Update password --}}
    <form wire:submit="updatePassword" class="card ms-mt16-stack-16">
        <div>
            <h3 class="ms-fs-18">{{ __('profile.update_password') }}</h3>
            <p class="text-muted ms-note">{{ __('profile.update_password_description') }}</p>
        </div>

        <div data-show-on="password-updated" class="tag tag-accent">
            <span class="ms-block-pad">{{ __('profile.password_updated') }}</span>
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="current_password" value="Current password" />
            <x-text-input wire:model="current_password" id="current_password" type="password" autocomplete="current-password" :error="$errors->first('current_password')" />
            <x-input-error :message="$errors->first('current_password')" />
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="password" value="New password" />
            <x-text-input wire:model="password" id="password" type="password" autocomplete="new-password" :error="$errors->first('password')" />
            <x-input-error :message="$errors->first('password')" />
        </div>

        <div class="field ms-mb-0">
            <x-input-label for="password_confirmation" value="Confirm new password" />
            <x-text-input wire:model="password_confirmation" id="password_confirmation" type="password" autocomplete="new-password" />
        </div>

        <div>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="updatePassword">Update password</button>
        </div>
    </form>

    {{-- Delete account --}}
    <div class="card ms-mt16-stack-16">
        <div class="ms-row-between">
            <div>
                <h3 class="ms-fs-18">{{ __('two_factor.settings_title') }}</h3>
                <p class="text-muted ms-note">{{ __('two_factor.settings_description') }}</p>
            </div>
            <a href="{{ route('two-factor.settings') }}" wire:navigate class="btn">{{ __('two_factor.settings_title') }}</a>
        </div>
    </div>

    <div class="card ms-mt16-danger-stack">
        <div>
            <h3 class="ms-fs-18">{{ __('profile.delete_account') }}</h3>
            <p class="text-muted ms-note">
                {{ __('profile.delete_account_description') }}
            </p>
        </div>

        <form wire:submit="deleteAccount" class="ms-stack-16">
            <div class="field ms-mb0-mw320">
                <x-input-label for="delete_password" value="Password" />
                <x-text-input wire:model="delete_password" id="delete_password" type="password" autocomplete="current-password" :error="$errors->first('delete_password')" />
                <x-input-error :message="$errors->first('delete_password')" />
            </div>

            <div>
                <button
                    type="submit"
                    class="btn ms-outline-danger"
                    wire:confirm="Are you sure you want to permanently delete your account?"
                    wire:loading.attr="disabled"
                    wire:target="deleteAccount"
                >
                    {{ __('profile.delete_account') }}
                </button>
            </div>
        </form>
    </div>
</div>

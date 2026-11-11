<?php

use App\Models\User;
use App\Services\UserService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    public ?User $user = null;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public array $roles = [];

    public function mount(?User $user = null): void
    {
        $this->authorize('manage users');

        $this->user = $user;
        $this->name = $user?->name ?? '';
        $this->email = $user?->email ?? '';
        $this->roles = $user?->roles->pluck('name')->all() ?? [];
    }

    #[Computed]
    public function availableRoles(): array
    {
        return app(UserService::class)->roleNames();
    }

    public function save(UserService $users): void
    {
        $this->authorize('manage users');

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $this->emailRules(),
            'password' => [$this->user ? 'nullable' : 'required', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($this->availableRoles)],
        ]);

        if ($this->user) {
            $users->updateUser($this->user, $validated, $this->roles);
            $message = 'User updated successfully.';
        } else {
            $users->create($validated, $this->roles);
            $message = 'User created successfully.';
        }

        session()->flash('status', $message);

        $this->redirect(route('admin.users.index'), navigate: true);
    }

    protected function emailRules(): array
    {
        $unique = Rule::unique('users', 'email');

        if ($this->user) {
            $unique->ignore($this->user);
        }

        return ['required', 'string', 'email', 'max:255', $unique];
    }
};
?>

<form wire:submit="save" class="card ms-stack-16">
    <div class="field ms-mb-0">
        <x-input-label for="name" value="Full name" />
        <x-text-input wire:model="name" id="name" type="text" autofocus :error="$errors->first('name')" />
        <x-input-error :message="$errors->first('name')" />
    </div>

    <div class="field ms-mb-0">
        <x-input-label for="email" value="Email" />
        <x-text-input wire:model="email" id="email" type="email" :error="$errors->first('email')" />
        <x-input-error :message="$errors->first('email')" />
    </div>

    <div class="field ms-mb-0">
        <x-input-label for="password" :value="$user ? 'New password' : 'Password'" />
        <x-text-input
            wire:model="password"
            id="password"
            type="password"
            autocomplete="new-password"
            :placeholder="$user ? __('users.password_placeholder') : null"
            :error="$errors->first('password')"
        />
        <x-input-error :message="$errors->first('password')" />
    </div>

    <div class="field ms-mb-0">
        <x-input-label value="Roles" />
        <div class="ms-radio-stack">
            @forelse ($this->availableRoles as $role)
                <label class="ms-check-label">
                    <input type="checkbox" wire:model="roles" value="{{ $role }}" class="checkbox">
                    {{ Str::headline($role) }}
                </label>
            @empty
                <p class="text-muted ms-note">{{ __('users.no_roles') }}</p>
            @endforelse
        </div>
    </div>

    <div class="ms-row-10">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            {{ $user ? __('users.save_changes') : __('users.create') }}
        </button>
        <a href="{{ route('admin.users.index') }}" wire:navigate class="btn">{{ __('actions.cancel') }}</a>
    </div>
</form>

<?php

use App\Models\User;
use App\Services\SettingService;
use App\Services\UserService;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Users')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    /** all | admin | member */
    #[Url(as: 'role', history: true)]
    public string $role = 'all';

    /** active | trashed */
    #[Url(as: 'view', history: true)]
    public string $listing = 'active';

    /** all | active | suspended */
    #[Url(as: 'status', history: true)]
    public string $status = 'all';

    /** name | created_at */
    #[Url(as: 'sort', history: true)]
    public string $sort = 'created_at';

    #[Url(as: 'dir', history: true)]
    public string $direction = 'desc';

    public function mount(): void
    {
        $this->authorize('manage users');
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'role', 'listing', 'status', 'sort', 'direction'], true)) {
            $this->resetPage();
        }
    }

    public function sortBy(string $column): void
    {
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function toggleAdmin(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        $target = User::findOrFail($userId);

        try {
            $users->toggleAdminRole(auth()->user(), $target);
        } catch (\DomainException $e) {
            $this->addError('toggle', $e->getMessage());
        }
    }

    public function deleteUser(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        $target = User::findOrFail($userId);

        try {
            $users->delete(auth()->user(), $target);
        } catch (\DomainException $e) {
            $this->addError('toggle', $e->getMessage());
        }
    }

    public function suspendUser(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        try {
            $users->suspend(auth()->user(), User::findOrFail($userId));
            $this->dispatch('notify', type: 'success', message: __('account.user_suspended'));
        } catch (\DomainException $e) {
            $this->addError('toggle', $e->getMessage());
        }
    }

    public function reactivateUser(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        $users->reactivate(User::findOrFail($userId));
        $this->dispatch('notify', type: 'success', message: __('account.user_reactivated'));
    }

    public function restoreUser(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        $users->restore(User::onlyTrashed()->findOrFail($userId));
    }

    public function forceDeleteUser(int $userId, UserService $users): void
    {
        $this->authorize('manage users');

        try {
            $users->forceDelete(auth()->user(), User::onlyTrashed()->findOrFail($userId));
        } catch (\DomainException $e) {
            $this->addError('toggle', $e->getMessage());
        }
    }

    #[Computed]
    public function dateFormat(): string
    {
        return app(SettingService::class)->dateFormat();
    }

    #[Computed]
    public function users()
    {
        return app(UserService::class)->searchPaginated($this->search, 8, [
            'role' => in_array($this->role, ['admin', 'member'], true) ? $this->role : null,
            'trashed' => $this->listing === 'trashed' ? 'only' : null,
            'status' => in_array($this->status, ['active', 'suspended'], true) ? $this->status : null,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ]);
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('app.users') }}</h2>
            <p class="text-muted ms-note">{{ __('dashboard.users_description') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            <a href="{{ route('admin.users.create') }}" wire:navigate class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M19 8v6M16 11h6"></path></svg>
                New user
            </a>
        </div>
    </div>

    @if (session('status'))
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    <div class="ms-toolbar">
        <div class="ms-search-field">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="rgba(var(--ink),.5)" stroke-width="1.5" class="ms-input-icon"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4-4"></path></svg>
            <input wire:model.live.debounce.300ms="search" class="input ms-pl-32" placeholder="{{ __('dashboard.search_placeholder') }}">
        </div>

        <div class="ms-row-10">
            <select wire:model.live="listing" class="input" aria-label="View">
                <option value="active">Active users</option>
                <option value="trashed">Trashed users</option>
            </select>
            <select wire:model.live="role" class="input" aria-label="Role">
                <option value="all">{{ __('dashboard.all_roles') }}</option>
                <option value="admin">Administrators</option>
                <option value="member">{{ __('dashboard.member') }}</option>
            </select>
            <select wire:model.live="status" class="input" aria-label="Status">
                <option value="all">{{ __('account.all_statuses') }}</option>
                <option value="active">{{ __('account.status_active') }}</option>
                <option value="suspended">{{ __('account.status_suspended') }}</option>
            </select>
        </div>
    </div>

    @error('toggle')
        <div class="tag tag-outline ms-notice">{{ $message }}</div>
    @enderror

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                <div class="row-cards-toolbar">
                    <div class="row-cards-control"><button type="button" wire:click="sortBy('name')" class="ms-sort-btn">{{ __('dashboard.user') }}</button></div>
                    <div class="row-cards-control"><button type="button" wire:click="sortBy('created_at')" class="ms-sort-btn">{{ __('dashboard.joined') }}</button></div>
                </div>
                    @forelse ($this->users as $user)
                        <div class="row-card" wire:key="user-{{ $user->id }}">
                            <div class="row-card-title">
                                <div class="ms-row-10-center">
                                    <x-user-avatar :user="$user" />
                                    <div class="ms-minw-0">
                                        <div class="ms-fw-500">{{ $user->name }}</div>
                                        <div class="text-muted ms-fs-12">{{ $user->email }}</div>
                                    </div>
                                </div>
                            </div>
                            <div class="row-card-field text-muted ms-nowrap" data-label="{{ __('dashboard.joined') }}">{{ $user->created_at->format($this->dateFormat) }}</div>
                            <div class="row-card-field" data-label="{{ __('dashboard.role') }}">
                                @if ($user->trashed())
                                    <span class="tag tag-outline">Trashed</span>
                                @else
                                    @forelse ($user->roles as $role)
                                        <span class="tag {{ $role->name === 'admin' ? 'tag-accent' : 'tag-neutral' }}">{{ __('fakkroun.roles.'.$role->name) }}</span>
                                    @empty
                                        <span class="tag tag-neutral">Member</span>
                                    @endforelse
                                    @if ($user->isSuspended())
                                        <span class="tag tag-outline">{{ __('account.status_suspended') }}</span>
                                    @endif
                                @endif
                            </div>
                            <div class="row-card-actions">
                                <div class="ms-actions-end">
                                    @if ($user->trashed())
                                        <button type="button" wire:click="restoreUser({{ $user->id }})" class="btn ms-btn-sm">Restore</button>
                                        <button
                                            type="button"
                                            wire:click="forceDeleteUser({{ $user->id }})"
                                            wire:confirm="Permanently delete {{ $user->name }}? This cannot be undone."
                                            @disabled($user->id === auth()->id())
                                            class="btn ms-btn-sm-danger"
                                        >Delete permanently</button>
                                    @else
                                        <button
                                            type="button"
                                            wire:click="toggleAdmin({{ $user->id }})"
                                            wire:confirm="Are you sure you want to {{ $user->hasRole('admin') ? 'remove admin access from' : 'grant admin access to' }} {{ $user->name }}?"
                                            @disabled($user->id === auth()->id())
                                            class="btn ms-btn-sm"
                                        >
                                            {{ $user->hasRole('admin') ? __('dashboard.revoke_admin') : __('dashboard.make_admin') }}
                                        </button>
                                        @if ($user->isSuspended())
                                            <button type="button" wire:click="reactivateUser({{ $user->id }})" class="btn ms-btn-sm">{{ __('account.reactivate') }}</button>
                                        @else
                                            <button
                                                type="button"
                                                wire:click="suspendUser({{ $user->id }})"
                                                wire:confirm="{{ __('account.suspend_confirm', ['name' => $user->name]) }}"
                                                @disabled($user->id === auth()->id())
                                                class="btn ms-btn-sm"
                                            >{{ __('account.suspend') }}</button>
                                        @endif
                                        <a
                                            href="{{ route('admin.users.edit', $user) }}"
                                            wire:navigate
                                            class="btn ms-btn-sm"
                                        >Edit</a>
                                        <button
                                            type="button"
                                            wire:click="deleteUser({{ $user->id }})"
                                            wire:confirm="Move {{ $user->name }} to the trash? They will not be able to sign in until restored."
                                            @disabled($user->id === auth()->id())
                                            class="btn ms-btn-sm-danger"
                                        >Move to trash</button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('dashboard.no_users_found') }}</div>
                    @endforelse
            </div>
        </div>

        <div class="ms-cell-top">
            {{ $this->users->links() }}
        </div>
    </div>
</div>

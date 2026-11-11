<?php

use App\Services\NotificationService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Notifications')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** all | unread */
    #[Url(as: 'status', history: true)]
    public string $filter = 'all';

    public function updatedFilter(): void
    {
        $this->resetPage();
    }

    /**
     * Scoped to the signed-in user's own notifications, so an id belonging to
     * someone else resolves to a 404.
     */
    public function markAsRead(string $id, NotificationService $notifications): void
    {
        $notifications->markAsRead(auth()->user(), $id);
    }

    public function markAllAsRead(NotificationService $notifications): void
    {
        $notifications->markAllAsRead(auth()->user());

        $this->toast(__('notifications.all_marked_read'));
    }

    #[Computed]
    public function notifications()
    {
        return app(NotificationService::class)->paginateFor(auth()->user(), 15, $this->filter === 'unread');
    }

    #[Computed]
    public function unreadCount(): int
    {
        return app(NotificationService::class)->unreadCount(auth()->user());
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
            <h2>{{ __('notifications.heading') }}</h2>
            <p class="text-muted ms-note">{{ __('notifications.description') }}</p>
        </div>
        <button
            type="button"
            wire:click="markAllAsRead"
            wire:loading.attr="disabled"
            class="btn"
            @disabled($this->unreadCount === 0)
        >{{ __('notifications.mark_all_read') }}</button>
    </div>

    <div class="ms-toolbar">
        <div class="ms-row-10">
            <button type="button" wire:click="$set('filter', 'all')" class="btn {{ $filter === 'all' ? 'btn-primary' : '' }}">
                {{ __('notifications.filter_all') }}
            </button>
            <button type="button" wire:click="$set('filter', 'unread')" class="btn {{ $filter === 'unread' ? 'btn-primary' : '' }}">
                {{ __('notifications.filter_unread') }}
                @if ($this->unreadCount > 0)
                    <span class="tag tag-accent">{{ $this->unreadCount }}</span>
                @endif
            </button>
        </div>
    </div>

    <div class="card ms-p-0">
        @forelse ($this->notifications as $notification)
            <div class="notification-row {{ is_null($notification->read_at) ? 'is-unread' : '' }}" wire:key="notification-{{ $notification->id }}">
                <div>
                    <div class="ms-row-10">
                        <span class="ms-strong-13">{{ $notification->data['title'] ?? '' }}</span>
                        @if (is_null($notification->read_at))
                            <span class="tag tag-accent">{{ __('notifications.new') }}</span>
                        @endif
                    </div>
                    <div class="text-muted ms-fs-13">{{ $notification->data['message'] ?? '' }}</div>
                    <div class="text-muted ms-fs-12">{{ $notification->created_at->diffForHumans() }}</div>
                </div>

                <div class="ms-row-10">
                    @if (! empty($notification->data['action_url']))
                        <a href="{{ $notification->data['action_url'] }}" wire:navigate class="btn ms-btn-sm">{{ __('notifications.open') }}</a>
                    @endif
                    @if (is_null($notification->read_at))
                        <button type="button" wire:click="markAsRead('{{ $notification->id }}')" class="btn ms-btn-sm">
                            {{ __('notifications.mark_read') }}
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-muted notification-empty">
                {{ $filter === 'unread' ? __('notifications.empty_unread') : __('notifications.empty') }}
            </p>
        @endforelse
    </div>

    {{ $this->notifications->links() }}
</div>

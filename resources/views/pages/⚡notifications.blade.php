<?php

use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/** Player inbox for in-app notifications (spec §23; next step 11). */
new #[Layout('layouts::app')] #[Title('Notifications')] class extends Component
{
    use WithPagination;

    #[Computed]
    public function notifications()
    {
        return auth()->user()->notifications()->latest()->paginate(15);
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function open(string $id): void
    {
        $notification = auth()->user()->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        $url = $notification->data['url'] ?? null;

        if (is_string($url) && str_starts_with($url, url('/'))) {
            $this->redirect($url, navigate: true);
        }
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('play.inbox.title') }}</h2>
            <p class="text-muted ms-note">{{ __('play.inbox.description') }}</p>
        </div>
        <button type="button" wire:click="markAllRead" class="btn">{{ __('play.inbox.mark_all') }}</button>
    </div>
    <div class="card ms-p-0">
        <ul class="ms-stack-16" style="padding: 8px 16px;">
            @forelse ($this->notifications as $notification)
                <li wire:key="n-{{ $notification->id }}" class="ms-row-between">
                    <button type="button" wire:click="open('{{ $notification->id }}')" class="ms-linkbtn" style="text-align: start;">
                        <span class="{{ $notification->read_at ? '' : 'ms-fw-500' }}">{{ $notification->data['title'] ?? __('play.inbox.untitled') }}</span>
                        @if (! empty($notification->data['message']))<span class="text-muted ms-block-fs12">{{ $notification->data['message'] }}</span>@endif
                        @if (! empty($notification->data['reason']))<span class="text-muted ms-block-fs12">{{ $notification->data['reason'] }}</span>@endif
                    </button>
                    <span class="text-muted ms-fs-12 ms-nowrap">{{ $notification->created_at->diffForHumans() }}</span>
                </li>
            @empty
                <li class="text-muted ms-empty">{{ __('play.inbox.empty') }}</li>
            @endforelse
        </ul>
        <div class="ms-cell-top">{{ $this->notifications->links() }}</div>
    </div>
</div>

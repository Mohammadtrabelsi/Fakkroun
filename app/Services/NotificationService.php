<?php

namespace App\Services;

use App\Models\User;
use App\Services\Concerns\ManagesModel;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * Single entry point for reading and writing a user's database notifications.
 */
class NotificationService
{
    /** @use ManagesModel<DatabaseNotification> */
    use ManagesModel;

    public function paginateFor(User $user, int $perPage = 15, bool $unreadOnly = false): LengthAwarePaginator
    {
        return $user->notifications()
            ->when($unreadOnly, fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate($perPage);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * Marks one of the user's notifications as read; another user's id is a 404.
     */
    public function markAsRead(User $user, string $id): DatabaseNotification
    {
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }

    public function markAllAsRead(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }

    protected function model(): string
    {
        return DatabaseNotification::class;
    }
}

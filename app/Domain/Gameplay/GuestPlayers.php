<?php

namespace App\Domain\Gameplay;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Nickname-only players. Joining a room needs no account: the join page
 * creates a throwaway, signed-in guest user so the game engine (which works
 * on user ids) runs unchanged. Guests can play but cannot create rooms or open
 * account pages (EnsureUserIsRegistered), and are pruned after a week.
 */
class GuestPlayers
{
    public const EMAIL_DOMAIN = 'guests.fakkroun.invalid';

    public function create(string $nickname): User
    {
        // No model events: guests must not trigger "user created" admin
        // notifications or activity-log entries.
        $user = User::withoutEvents(fn () => tap(new User, function (User $user) use ($nickname): void {
            $user->forceFill([
                'name' => Str::squish($nickname),
                'email' => 'guest-'.Str::lower((string) Str::ulid()).'@'.self::EMAIL_DOMAIN,
                'password' => Hash::make(Str::random(40)),
                'email_verified_at' => now(),
                'is_guest' => true,
                'locale' => app()->getLocale(),
            ])->save();
        }));

        $user->assignRole(UserRole::Player->value);

        return $user;
    }
}

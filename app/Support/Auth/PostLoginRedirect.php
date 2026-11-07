<?php

namespace App\Support\Auth;

use App\Models\User;

final class PostLoginRedirect
{
    /**
     * Where a freshly signed-in user lands: the page that sent them to log in
     * (e.g. a room invitation), else their portal's home.
     */
    public static function urlFor(User $user): string
    {
        $default = $user->canAccessBackoffice() ? $user->backofficeHomeUrl() : route('dashboard');

        return (string) session()->pull('url.intended', $default);
    }
}

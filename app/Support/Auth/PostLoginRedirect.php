<?php

namespace App\Support\Auth;

use App\Models\User;

final class PostLoginRedirect
{
    /**
     * Where a freshly signed-in user lands.
     *
     * Front-office sign-in (player portal) always stays on the front: the page
     * that sent them to log in (e.g. a room invitation) when it is a front page,
     * otherwise the player dashboard. Back-office URLs are never used there.
     * Staff sign-in (staff portal) always lands in the back office: the back-office
     * page they were sent to, else their back-office home.
     */
    public static function urlFor(User $user, string $portal): string
    {
        $intended = (string) session()->pull('url.intended', '');

        if ($portal === AuthenticatesIntoPortal::PORTAL_PLAYER) {
            return $intended !== '' && ! self::isBackofficeUrl($intended)
                ? $intended
                : route('dashboard');
        }

        if (! $user->canAccessBackoffice()) {
            return route('dashboard');
        }

        return $intended !== '' && self::isBackofficeUrl($intended)
            ? $intended
            : $user->backofficeHomeUrl();
    }

    private static function isBackofficeUrl(string $url): bool
    {
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $path === '/admin' || str_starts_with($path, '/admin/');
    }
}

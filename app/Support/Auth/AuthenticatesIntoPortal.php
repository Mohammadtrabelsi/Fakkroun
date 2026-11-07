<?php

namespace App\Support\Auth;

use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\UserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Shared sign-in flow for the two login pages:
 *
 *  - the player portal (/login, front office) only admits accounts without
 *    back-office access;
 *  - the staff portal (/admin/login, back office) only admits admins,
 *    question writers and reviewers (anyone holding `access backoffice`).
 *
 * Used by Livewire components exposing `$email`, `$password` and `$remember`.
 */
trait AuthenticatesIntoPortal
{
    public const PORTAL_PLAYER = 'player';

    public const PORTAL_STAFF = 'staff';

    protected function attemptPortalLogin(string $portal): void
    {
        $this->validate();

        $throttleKey = Str::lower($this->email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => __('Too many login attempts. Please try again in :seconds seconds.', ['seconds' => RateLimiter::availableIn($throttleKey)]),
            ]);
        }

        $user = app(UserService::class)->authenticate($this->email, $this->password);

        if ($user === null) {
            RateLimiter::hit($throttleKey);

            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if ($portal === self::PORTAL_PLAYER && $user->canAccessBackoffice()) {
            throw ValidationException::withMessages(['email' => __('login.use_staff_portal')]);
        }

        if ($portal === self::PORTAL_STAFF && ! $user->canAccessBackoffice()) {
            throw ValidationException::withMessages(['email' => __('login.not_staff')]);
        }

        RateLimiter::clear($throttleKey);

        // With two-factor enabled the session is not established yet: the user
        // must first prove possession of the second factor on the challenge page.
        if ($user->hasTwoFactorEnabled()) {
            session()->put(TwoFactorService::CHALLENGE_SESSION_KEY, [
                'id' => $user->id,
                'remember' => $this->remember,
            ]);

            $this->redirect(route('two-factor.challenge'), navigate: true);

            return;
        }

        Auth::login($user, $this->remember);

        if (request()->hasSession()) {
            request()->session()->regenerate();
        }

        // Full page load: the two portals use different layouts and the target
        // may be a plain controller route (e.g. /join/{code}).
        $this->redirect(PostLoginRedirect::urlFor($user));
    }
}

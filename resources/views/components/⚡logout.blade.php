<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

/**
 * Sign-out button. `variant="menu"` renders the account-menu entry of the
 * app shell; the default is a plain button (verify-email screen).
 */
new class extends Component
{
    public string $variant = 'button';

    public string $label = '';

    public function logout(): void
    {
        $wasStaff = (bool) auth()->user()?->canAccessBackoffice();

        Auth::guard('web')->logout();

        session()->invalidate();
        session()->regenerateToken();

        // Staff return to the staff portal, players to the front office.
        $this->redirect($wasStaff ? route('admin.login') : '/');
    }
};
?>

<form wire:submit="logout" @class(['ms-border-top' => $variant === 'menu'])>
    @if ($variant === 'menu')
        <button type="submit" class="ms-menu-btn-danger">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            {{ $label ?: __('app.logout') }}
        </button>
    @else
        <button type="submit" class="btn">{{ $label ?: __('app.logout') }}</button>
    @endif
</form>

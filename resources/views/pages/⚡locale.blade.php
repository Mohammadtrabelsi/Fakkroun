<?php

use App\Services\UserService;
use Livewire\Component;

/**
 * /language/{locale}: stores the chosen interface language, then sends the
 * visitor back where they came from. Plain links point here, so switching
 * works from the JS-free marketing layout too.
 */
new class extends Component
{
    public function mount(string $locale, UserService $users): void
    {
        if (array_key_exists($locale, config('app.available_locales', []))) {
            session()->put('locale', $locale);

            // Signed-in users keep the choice across sessions and devices.
            if ($user = auth()->user()) {
                $users->updatePreferences($user, ['locale' => $locale]);
            }
        }

        $this->redirect(url()->previous());
    }
};
?>

<div></div>

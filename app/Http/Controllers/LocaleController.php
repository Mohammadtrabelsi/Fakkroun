<?php

namespace App\Http\Controllers;

use App\Services\UserService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        if (array_key_exists($locale, config('app.available_locales', []))) {
            $request->session()->put('locale', $locale);

            // Signed-in users keep the choice across sessions and devices.
            if ($user = $request->user()) {
                $this->users->updatePreferences($user, ['locale' => $locale]);
            }
        }

        return redirect()->back();
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * Return the authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->load('roles'));
    }

    /**
     * UserService::updateProfile
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [$user->phone ? 'nullable' : 'required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ]);

        return response()->json($this->users->updateProfile($user, $validated));
    }

    /**
     * UserService::updatePreferences (interface and question language)
     */
    public function preferences(Request $request): JsonResponse
    {
        $locales = array_keys((array) config('app.available_locales', []));

        $validated = $request->validate([
            'locale' => ['sometimes', 'nullable', Rule::in($locales)],
            'question_locale' => ['sometimes', 'nullable', Rule::in($locales)],
        ]);

        return response()->json($this->users->updatePreferences($request->user(), $validated));
    }

    /**
     * UserService::updatePassword
     */
    public function password(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $this->users->updatePassword($request->user(), $validated['password']);

        return response()->json(['message' => 'Password updated.']);
    }

    /**
     * UserService::deleteOwnAccount
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'password' => ['required', 'string', 'current_password'],
        ]);

        $this->users->deleteOwnAccount($request->user());

        return response()->json(null, 204);
    }
}

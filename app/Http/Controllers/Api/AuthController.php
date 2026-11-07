<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TwoFactorService;
use App\Services\UserService;
use App\Support\Auth\LoginIdentifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        private readonly UserService $users,
        private readonly TwoFactorService $twoFactor,
    ) {}

    /**
     * UserService::register
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Email or phone number; no email verification before playing.
            'email' => ['nullable', 'required_without:phone', 'string', 'email', 'max:255', 'unique:users,email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32', $this->phoneRule()],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->users->register($validated);

        return response()->json($user, 201);
    }

    /**
     * UserService::authenticate, then a session login (completes two-factor when enabled)
     */
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'required_without:phone', 'string', 'email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
            'two_factor_code' => ['nullable', 'string'],
        ]);

        $user = $this->authenticatedUser($validated);

        Auth::login($user, $validated['remember'] ?? false);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        return response()->json([
            'message' => 'Authenticated.',
            'user' => Auth::user(),
        ]);
    }

    /**
     * Issues a personal access token (Sanctum) for bearer-token API clients.
     */
    public function token(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'required_without:phone', 'string', 'email'],
            'phone' => ['nullable', 'required_without:email', 'string', 'max:32'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string'],
        ]);

        $user = $this->authenticatedUser($validated);

        return response()->json([
            'token' => $user->createToken($validated['device_name'])->plainTextToken,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * Revokes the bearer token used for this request. Session logins have no token to revoke.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(null, 204);
    }

    /** Valid and not already registered (stored in normalized form). */
    private function phoneRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            if (! LoginIdentifier::isValidPhone((string) $value)) {
                $fail(__('register.invalid_login'));
            } elseif (User::withTrashed()->where('phone', LoginIdentifier::normalizePhone((string) $value))->exists()) {
                $fail(__('register.phone_taken'));
            }
        };
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function authenticatedUser(array $validated): User
    {
        $login = filled($validated['email'] ?? null) ? $validated['email'] : (string) ($validated['phone'] ?? '');

        $user = $this->users->authenticate($login, $validated['password']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => __('These credentials do not match our records.'),
            ]);
        }

        if (! $this->twoFactor->verifyLogin($user, $validated['two_factor_code'] ?? null)) {
            throw ValidationException::withMessages([
                'two_factor_code' => __('A valid two-factor code is required.'),
            ]);
        }

        return $user;
    }
}

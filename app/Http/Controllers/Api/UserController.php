<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    public function __construct(private readonly UserService $users) {}

    /**
     * UserService::searchPaginated
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'role' => ['nullable', 'in:admin,member'],
            'trashed' => ['nullable', 'in:only,with'],
            'sort' => ['nullable', 'in:name,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return response()->json(
            $this->users->searchPaginated(
                $validated['search'] ?? null,
                $validated['per_page'] ?? 8,
                collect($validated)->only(['role', 'trashed', 'sort', 'direction'])->all(),
            )
        );
    }

    /**
     * UserService::roleNames
     */
    public function roles(): JsonResponse
    {
        return response()->json($this->users->roleNames());
    }

    /**
     * UserService::stats
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->users->stats());
    }

    /**
     * UserService::create
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($this->users->roleNames())],
        ]);

        $user = $this->users->create($validated, $validated['roles'] ?? []);

        return response()->json($user->load('roles'), 201);
    }

    /**
     * UserService::updateUser
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', Password::defaults()],
            'roles' => ['array'],
            'roles.*' => ['string', Rule::in($this->users->roleNames())],
        ]);

        $user = $this->users->updateUser($user, $validated, $validated['roles'] ?? []);

        return response()->json($user->load('roles'));
    }

    /**
     * UserService::delete
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        // Guard-rail violations throw DomainActionException, which renders
        // itself as a 422 JSON response (see App\Exceptions).
        $this->users->delete($request->user(), $user);

        return response()->json(null, 204);
    }

    /**
     * UserService::restore (route binds trashed users)
     */
    public function restore(User $user): JsonResponse
    {
        return response()->json($this->users->restore($user)->load('roles'));
    }

    /**
     * UserService::forceDelete (route binds trashed users)
     */
    public function forceDestroy(Request $request, User $user): JsonResponse
    {
        $this->users->forceDelete($request->user(), $user);

        return response()->json(null, 204);
    }

    /**
     * UserService::toggleAdminRole
     */
    public function toggleAdmin(Request $request, User $user): JsonResponse
    {
        $this->users->toggleAdminRole($request->user(), $user);

        return response()->json($user->fresh()->load('roles'));
    }

    /**
     * UserService::suspend
     */
    public function suspend(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $this->users->suspend($request->user(), $user, $validated['reason'] ?? null);

        return response()->json($user->fresh());
    }

    /**
     * UserService::reactivate
     */
    public function reactivate(User $user): JsonResponse
    {
        return response()->json($this->users->reactivate($user));
    }
}

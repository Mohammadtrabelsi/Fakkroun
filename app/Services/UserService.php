<?php

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

class UserService
{
    public function register(array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            // Remember the language the visitor signed up in.
            'locale' => array_key_exists(app()->getLocale(), (array) config('app.available_locales', []))
                ? app()->getLocale()
                : null,
        ]);

        $user->assignRole(Role::findOrCreate(UserRole::default()->value, 'web'));

        event(new Registered($user));

        return $user;
    }

    public function attempt(string $email, string $password, bool $remember = false): bool
    {
        $user = $this->authenticate($email, $password);

        if ($user === null) {
            return false;
        }

        Auth::login($user, $remember);

        return true;
    }

    /**
     * Checks the credentials without logging the user in. Used by flows that
     * must verify a second factor before the session is established.
     */
    public function authenticate(string $email, string $password): ?User
    {
        if (! Auth::validate(['email' => $email, 'password' => $password])) {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        // Only reported after the password matched, so it reveals nothing to
        // someone guessing addresses.
        if ($user?->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => __('account.suspended'),
            ]);
        }

        return $user;
    }

    /**
     * Suspends an account: it can no longer sign in, its API tokens are revoked
     * and any open session is ended on its next request (EnsureAccountIsActive).
     */
    public function suspend(User $actor, User $target, ?string $reason = null): User
    {
        if ($actor->is($target)) {
            throw new DomainActionException('You cannot suspend your own account.');
        }

        DB::transaction(function () use ($actor, $target, $reason): void {
            $target->forceFill([
                'status' => AccountStatus::Suspended,
                'suspended_at' => now(),
                'suspension_reason' => $reason !== null && trim($reason) !== '' ? trim($reason) : null,
                'suspended_by' => $actor->id,
            ])->save();

            $target->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table((string) config('session.table', 'sessions'))->where('user_id', $target->id)->delete();
            }
        });

        return $target;
    }

    public function reactivate(User $target): User
    {
        $target->forceFill([
            'status' => AccountStatus::Active,
            'suspended_at' => null,
            'suspension_reason' => null,
            'suspended_by' => null,
        ])->save();

        return $target;
    }

    /**
     * Interface and question languages (spec §5.1). Null resets to the default.
     *
     * @param  array{locale?: string|null, question_locale?: string|null}  $data
     */
    public function updatePreferences(User $user, array $data): User
    {
        $supported = array_keys((array) config('app.available_locales', []));

        foreach (['locale', 'question_locale'] as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $value = $data[$field] === '' ? null : $data[$field];

            if ($value !== null && ! in_array($value, $supported, true)) {
                throw new DomainActionException('Unsupported language.');
            }

            $user->{$field} = $value;
        }

        $user->save();

        return $user;
    }

    /**
     * Stores a new profile picture on the public disk and removes the old one.
     * The file must already be validated by the caller (image, size limits).
     */
    public function updateAvatar(User $user, UploadedFile $file): User
    {
        $previous = $user->avatar_path;

        $user->avatar_path = $file->storePublicly('avatars', 'public');
        $user->save();

        if ($previous !== null && $previous !== $user->avatar_path) {
            Storage::disk('public')->delete($previous);
        }

        return $user;
    }

    public function removeAvatar(User $user): User
    {
        if ($user->avatar_path !== null) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->avatar_path = null;
            $user->save();
        }

        return $user;
    }

    public function toggleAdminRole(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new DomainActionException('You cannot change your own admin status.');
        }

        $target->hasRole('admin')
            ? $target->removeRole('admin')
            : $target->assignRole('admin');
    }

    public function create(array $data, array $roles = []): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        $user->syncRoles($roles);

        return $user;
    }

    public function updateUser(User $user, array $data, array $roles = []): User
    {
        if (array_key_exists('email', $data) && $data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();
        $user->syncRoles($roles);

        return $user;
    }

    /**
     * Soft delete: the account is moved to the trash and cannot sign in until restored.
     */
    public function delete(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new DomainActionException('You cannot delete your own account here.');
        }

        $target->delete();
    }

    public function restore(User $target): User
    {
        $target->restore();

        return $target;
    }

    public function forceDelete(User $actor, User $target): void
    {
        if ($actor->is($target)) {
            throw new DomainActionException('You cannot delete your own account here.');
        }

        $target->forceDelete();
    }

    public function updateProfile(User $user, array $data): User
    {
        $emailChanged = $data['email'] !== $user->email;

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
        ]);

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        return $user;
    }

    public function updatePassword(User $user, string $password): User
    {
        $user->password = Hash::make($password);
        $user->save();

        return $user;
    }

    public function deleteOwnAccount(User $user): void
    {
        Auth::logout();

        // Fully tear down the session (not just the auth state) so no stale
        // session/CSRF token survives the account deletion.
        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $user->delete();
    }

    public function roleNames(): array
    {
        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * Admin listing. Supported filters: role (admin|member), status (active|suspended), trashed (only|with),
     * sort (name|created_at) and direction (asc|desc).
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchPaginated(?string $search, int $perPage = 8, array $filters = []): LengthAwarePaginator
    {
        $sort = ($filters['sort'] ?? null) === 'name' ? 'name' : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return User::query()
            ->with('roles')
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(($filters['role'] ?? null) === 'admin', fn ($query) => $query->role('admin'))
            ->when(($filters['role'] ?? null) === 'member', fn ($query) => $query->whereDoesntHave('roles', fn ($q) => $q->where('name', 'admin')))
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('status', AccountStatus::Active->value))
            ->when(($filters['status'] ?? null) === 'suspended', fn ($query) => $query->where('status', AccountStatus::Suspended->value))
            ->when($search, fn ($query) => $query->where(
                fn ($q) => $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
            ))
            ->orderBy($sort, $direction)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        // Collapse the three created_at counts into a single aggregate query
        // (the role-based admin count needs its own join, so it stays separate).
        $counts = User::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as new_this_week', [now()->subWeek()])
            ->selectRaw('sum(case when created_at >= ? then 1 else 0 end) as new_today', [now()->startOfDay()])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'admins' => User::role('admin')->count(),
            'newThisWeek' => (int) ($counts->new_this_week ?? 0),
            'newToday' => (int) ($counts->new_today ?? 0),
            'trashed' => User::onlyTrashed()->count(),
        ];
    }

    /**
     * Sign-ups per day for the last $days days (oldest first), zero-filled.
     *
     * @return array<int, array{date: Carbon, count: int}>
     */
    public function dailySignups(int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = User::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('date(created_at) as day, count(*) as total')
            ->groupBy(DB::raw('date(created_at)'))
            ->pluck('total', 'day');

        return collect(range(0, $days - 1))
            ->map(function (int $offset) use ($start, $counts) {
                $date = $start->copy()->addDays($offset);

                return ['date' => $date, 'count' => (int) ($counts[$date->toDateString()] ?? 0)];
            })
            ->all();
    }

    public function newThisMonth(): int
    {
        return User::query()->where('created_at', '>=', now()->startOfMonth())->count();
    }

    /**
     * Users per role, plus users holding no role at all (reported as null).
     * A user with several roles is counted once per role.
     *
     * @return array<int, array{role: string|null, count: int}>
     */
    public function roleDistribution(): array
    {
        $rows = Role::query()
            ->withCount('users')
            ->orderByDesc('users_count')
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role) => ['role' => $role->name, 'count' => (int) $role->getAttribute('users_count')])
            ->all();

        $withoutRole = User::query()->doesntHave('roles')->count();

        if ($withoutRole > 0) {
            $rows[] = ['role' => null, 'count' => $withoutRole];
        }

        return $rows;
    }
}

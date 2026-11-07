<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Services\PermissionService;
use App\Services\RoleService;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Permissions granted to each role. The admin role always receives every
     * permission listed anywhere in this map.
     *
     * Some permissions gate modules that arrive in later phases (questions,
     * games, advertising); they are seeded now so role definitions stay stable.
     *
     * @var array<string, array<int, string>>
     */
    public const ROLE_PERMISSIONS = [
        'admin' => [
            // Back office (inherited from MajidaStarter)
            'view admin dashboard',
            'manage users',
            'manage categories',
            'manage posts',
            'manage tags',
            'view activity log',
            'manage settings',
            'view system health',
            'manage backups',
            // Fakkroun
            'access backoffice',
            'manage platform settings',
            'manage moderators',
            'manage questions',
            'moderate questions',
            'manage advertising',
        ],
        'moderator' => [
            'access backoffice',
            'moderate questions',
            'play games',
            'submit questions',
        ],
        'advertiser' => [
            'manage own campaigns',
            'play games',
        ],
        'player' => [
            'play games',
            'submit questions',
        ],
    ];

    public function run(): void
    {
        $permissions = app(PermissionService::class);
        $roles = app(RoleService::class);
        $all = collect(self::ROLE_PERMISSIONS)->flatten()->unique()->values()->all();

        foreach ($all as $permission) {
            $permissions->findOrCreate($permission, 'web');
        }

        // Seeders run with model events muted, which skips the cache-busting
        // observer Spatie relies on, so the freshly created permissions
        // must be flushed into the registrar's cache manually.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (UserRole::cases() as $role) {
            $granted = $role === UserRole::Admin ? $all : self::ROLE_PERMISSIONS[$role->value];

            $roles->findOrCreate($role->value, 'web')->syncPermissions($granted);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

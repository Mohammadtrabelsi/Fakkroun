<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'view admin dashboard',
            'manage users',
            'manage categories',
            'manage posts',
            'manage tags',
            'view activity log',
            'manage settings',
            'view system health',
            'manage backups',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Seeders run with model events muted, which skips the cache-busting
        // observer Spatie relies on, so the freshly created permissions
        // must be flushed into the registrar's cache manually.
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Role::findOrCreate('admin', 'web')->syncPermissions($permissions);
    }
}

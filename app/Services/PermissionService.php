<?php

namespace App\Services;

use App\Services\Concerns\ManagesModel;
use Spatie\Permission\Models\Permission;

/**
 * Single entry point for reading and writing Permission records.
 */
class PermissionService
{
    /** @use ManagesModel<Permission> */
    use ManagesModel;

    /**
     * The permission with this name for the guard, created when missing.
     */
    public function findOrCreate(string $name, ?string $guard = null): Permission
    {
        return Permission::findOrCreate($name, $guard);
    }

    protected function model(): string
    {
        return Permission::class;
    }
}

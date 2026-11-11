<?php

namespace App\Services;

use App\Services\Concerns\ManagesModel;
use Spatie\Permission\Models\Role;

/**
 * Single entry point for reading and writing Role records.
 */
class RoleService
{
    /** @use ManagesModel<Role> */
    use ManagesModel;

    /**
     * The role with this name for the guard, created when missing.
     */
    public function findOrCreate(string $name, ?string $guard = null): Role
    {
        return Role::findOrCreate($name, $guard);
    }

    protected function model(): string
    {
        return Role::class;
    }
}

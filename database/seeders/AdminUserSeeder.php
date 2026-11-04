<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $email = env('ADMIN_EMAIL', 'admin@fakkroun.test');

        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Admin User'),
                'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
                'email_verified_at' => now(),
            ]
        );

        $user->assignRole(Role::findOrCreate('admin', 'web'));

        $this->command?->info("Admin user ready: {$email}");
    }
}

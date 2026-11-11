<?php

namespace Database\Seeders;

use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Creates the verified account for mohammad.trabelsi@gmail.com.
 *
 * Run with: php artisan db:seed --class=OwnerUserSeeder --force
 *
 * The password is never stored in the repository: set OWNER_PASSWORD before
 * seeding, or leave it unset and a random one is generated and printed once.
 * Re-running the seeder keeps the existing password unless OWNER_PASSWORD is set.
 */
class OwnerUserSeeder extends Seeder
{
    public const EMAIL = 'mohammad.trabelsi@gmail.com';

    public const NAME = 'Mohammad Trabelsi';

    public function run(): void
    {
        $password = env('OWNER_PASSWORD');
        $generated = null;

        $user = app(UserService::class)->withTrashed()->firstOrNew(['email' => self::EMAIL]);

        if (! $user->exists) {
            $user->name = self::NAME;
        }

        if (filled($password)) {
            $user->password = Hash::make($password);
        } elseif (! $user->exists) {
            $generated = Str::password(20);
            $user->password = Hash::make($generated);
        }

        $user->forceFill(['email_verified_at' => $user->email_verified_at ?? now()]);
        $user->save();

        if ($user->trashed()) {
            $user->restore();
        }

        $this->command?->info('Verified account ready: '.self::EMAIL);

        if ($generated !== null) {
            $this->command?->warn("Generated password (shown once, change it after signing in): {$generated}");
        }
    }
}

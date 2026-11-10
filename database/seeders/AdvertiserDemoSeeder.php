<?php

namespace Database\Seeders;

use App\Domain\Advertising\AdvertiserService;
use App\Domain\Advertising\Models\Advertiser;
use App\Enums\UserRole;
use App\Services\RoleService;
use App\Services\UserService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Sample advertisers (one per advertiser type) so the advertising back office
 * and the advertiser dashboard have something to show. Each gets its own
 * sign-in (password "password"). Idempotent: re-running updates them in place.
 */
class AdvertiserDemoSeeder extends Seeder
{
    /** @var list<array{name: string, type: string, email: string, website: string|null}> */
    public const ADVERTISERS = [
        ['name' => 'Carthage Books', 'type' => 'company', 'email' => 'ads@carthage-books.test', 'website' => 'https://carthage-books.test'],
        ['name' => 'Université de Sousse', 'type' => 'university', 'email' => 'communication@univ-sousse.test', 'website' => 'https://univ-sousse.test'],
        ['name' => 'École Ibn Khaldoun', 'type' => 'school', 'email' => 'contact@ecole-ibnkhaldoun.test', 'website' => null],
        ['name' => 'Green Tunisia', 'type' => 'ngo', 'email' => 'hello@green-tunisia.test', 'website' => 'https://green-tunisia.test'],
        ['name' => 'Club des Jeunes Savants', 'type' => 'association', 'email' => 'club@jeunes-savants.test', 'website' => null],
    ];

    public function run(): void
    {
        $role = app(RoleService::class)->findOrCreate(UserRole::Advertiser->value, 'web');

        foreach (self::ADVERTISERS as $data) {
            $user = app(UserService::class)->updateOrCreate(
                ['email' => $data['email']],
                ['name' => $data['name'], 'password' => Hash::make('password'), 'email_verified_at' => now()],
            );

            $user->assignRole($role);

            app(AdvertiserService::class)->updateOrCreate(
                ['user_id' => $user->id],
                ['name' => $data['name'], 'type' => $data['type'], 'contact_email' => $data['email'], 'website' => $data['website'], 'status' => 'active'],
            );
        }

        $this->command?->info(count(self::ADVERTISERS).' sample advertisers ready (password: password).');
    }
}

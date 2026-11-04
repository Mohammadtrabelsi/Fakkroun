<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Authentication baseline. AdminUserSeeder also seeds roles & permissions.
     * Always seeded, in every mode.
     *
     * @var array<int, class-string>
     */
    public const MINIMAL = [
        AdminUserSeeder::class,
    ];

    /**
     * Structural configuration / lookups: the initial Fakkroun categories.
     *
     * @var array<int, class-string>
     */
    public const REFERENCE = [
        FakkrounCategorySeeder::class,
    ];

    /**
     * Demo / sample seeders. Sample users are created inline in run() below.
     *
     * @var array<int, class-string>
     */
    public const DEMO = [];

    /**
     * Seed the application's database with full data (configuration + demo content).
     */
    public function run(): void
    {
        $this->call([
            ...self::MINIMAL,
            ...self::REFERENCE,
            ...self::DEMO,
        ]);

        // Demo users - full mode only.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory(10)->create();
    }
}

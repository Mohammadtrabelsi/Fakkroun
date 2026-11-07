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
     * Structural configuration / lookups: the initial Fakkroun categories and
     * the standard advertiser packs.
     *
     * @var array<int, class-string>
     */
    public const REFERENCE = [
        FakkrounCategorySeeder::class,
        AdvertisingPackageSeeder::class,
    ];

    /**
     * Demo / sample seeders: trilingual questions (a moderation queue sample
     * and a published, playable set) and one finished demo game with its
     * game_questions, answers and results. Sample users are created inline in
     * run() below, before these run.
     *
     * @var array<int, class-string>
     */
    public const DEMO = [
        FakkrounDemoQuestionSeeder::class,
        FakkrounPlayableQuestionSeeder::class,
        FakkrounDemoGameSeeder::class,
    ];

    /**
     * Seed the application's database with full data (configuration + demo content).
     */
    public function run(): void
    {
        $this->call([
            ...self::MINIMAL,
            ...self::REFERENCE,
        ]);

        // Demo users - full mode only. Created first: the demo game seats them.
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory(10)->create();

        $this->call(self::DEMO);
    }
}

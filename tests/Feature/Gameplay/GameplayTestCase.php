<?php

namespace Tests\Feature\Gameplay;

use App\Domain\Gameplay\Models\Room;
use App\Domain\Gameplay\RoomService;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class GameplayTestCase extends TestCase
{
    use RefreshDatabase;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->category = Category::factory()->create();
    }

    protected function player(string $name = 'Player'): User
    {
        return tap(User::factory()->create(['name' => $name]), fn (User $u) => $u->assignRole(UserRole::Player->value));
    }

    protected function publishQuestions(int $count, string $difficulty = 'easy', ?Category $category = null): void
    {
        Question::factory()->count($count)->published()->create([
            'category_id' => ($category ?? $this->category)->id,
            'difficulty' => $difficulty,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    protected function room(User $host, array $overrides = []): Room
    {
        return app(RoomService::class)->create($host, array_merge([
            'name' => 'Friday quiz',
            'category_ids' => [$this->category->id],
            'question_count' => 3,
            'seconds_per_question' => 20,
            'difficulty' => 'any',
            'language' => 'en',
            'duplicate_rule' => false,
            'max_players' => 4,
        ], $overrides));
    }
}

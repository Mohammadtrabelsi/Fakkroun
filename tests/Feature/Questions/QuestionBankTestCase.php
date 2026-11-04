<?php

namespace Tests\Feature\Questions;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class QuestionBankTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create(['validation_policy' => 'moderator', 'requires_reference' => false]);
    }

    protected function userWithRole(UserRole $role): User
    {
        return tap(User::factory()->create(), fn (User $user) => $user->assignRole($role->value));
    }

    /**
     * @return array<string, mixed>
     */
    protected function content(array $overrides = []): array
    {
        return array_merge([
            'category_id' => $this->category->id,
            'difficulty' => 'easy',
            'language' => 'en',
            'body' => 'Which ocean is the largest on Earth?',
            'choices' => ['Atlantic', 'Indian', 'Pacific', 'Arctic'],
            'correct' => 2,
            'explanation' => 'It covers about a third of the planet.',
            'reference_url' => 'https://en.wikipedia.org/wiki/Pacific_Ocean',
        ], $overrides);
    }
}

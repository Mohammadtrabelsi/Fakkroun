<?php

namespace Tests\Feature\Seeders;

use App\Domain\Gameplay\Models\Game;
use App\Domain\Gameplay\Models\GameQuestion;
use App\Domain\Gameplay\Models\GameResult;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionPoolEntry;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FakkrounDemoGameSeeder;
use Database\Seeders\FakkrounPlayableQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoGameSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_seed_publishes_playable_questions_and_a_finished_game(): void
    {
        $this->seed(DatabaseSeeder::class);

        $published = Question::query()->where('status', QuestionStatus::Published->value)->count();
        $this->assertSame(count(FakkrounPlayableQuestionSeeder::QUESTIONS), $published);

        foreach (['en', 'fr', 'ar'] as $language) {
            $this->assertSame($published, QuestionPoolEntry::query()->where('language', $language)->count());
        }

        $game = Game::query()->sole();
        $this->assertSame(Game::COMPLETED, $game->status);
        $this->assertSame($game->total_questions, GameQuestion::query()->where('game_id', $game->id)->count());
        $this->assertSame(4, GameResult::query()->where('game_id', $game->id)->count());
        $this->assertSame(1, GameResult::query()->where('game_id', $game->id)->min('rank'));
    }

    public function test_reseeding_is_idempotent(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->seed([FakkrounPlayableQuestionSeeder::class, FakkrounDemoGameSeeder::class]);

        $this->assertSame(count(FakkrounPlayableQuestionSeeder::QUESTIONS), Question::query()->where('status', QuestionStatus::Published->value)->count());
        $this->assertSame(1, Game::query()->count());
    }
}

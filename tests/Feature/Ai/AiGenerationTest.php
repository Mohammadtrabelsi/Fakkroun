<?php

namespace Tests\Feature\Ai;

use App\Domain\Ai\Drivers\AnthropicQuestionGenerator;
use App\Domain\Ai\Drivers\FakeQuestionGenerator;
use App\Domain\Ai\GenerationFailed;
use App\Domain\Ai\GenerationRequest;
use App\Domain\Ai\GenerationService;
use App\Domain\Ai\Models\AiGenerationBatch;
use App\Domain\Ai\QuestionDrafter;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\TestCase;

class AiGenerationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create(['requires_reference' => false]);
        config(['fakkroun.ai.driver' => 'fake']);
        FakeQuestionGenerator::$items = null;
    }

    protected function tearDown(): void
    {
        FakeQuestionGenerator::$items = null;

        parent::tearDown();
    }

    private function item(string $question, int $correct = 1, int $choices = 4): array
    {
        return [
            'question' => $question,
            'choices' => array_slice(['Posidonia oceanica', 'Zostera marina', 'Halophila stipulacea', 'Caulerpa taxifolia', 'Extra'], 0, $choices),
            'correct_index' => $correct,
            'explanation' => 'Because.',
            'reference_url' => '',
        ];
    }

    private function anthropicReply(array $questions, string $stop = 'end_turn'): array
    {
        return [
            'id' => 'msg_1',
            'model' => 'claude-opus-5-5',
            'stop_reason' => $stop,
            'content' => [['type' => 'text', 'text' => json_encode(['questions' => $questions])]],
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 800],
        ];
    }

    public function test_anthropic_driver_sends_a_structured_output_request_and_parses_the_reply(): void
    {
        config(['fakkroun.ai.api_key' => 'test-key', 'fakkroun.ai.driver' => 'anthropic']);
        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicReply([$this->item('Which seagrass grows only in the Mediterranean?')]))]);

        $result = (new AnthropicQuestionGenerator)->generate(new GenerationRequest(1, 'en', 'medium', 'Marine Biodiversity', fact: 'Posidonia is endemic'));

        $this->assertCount(1, $result->items);
        $this->assertSame(1200, $result->inputTokens);
        $this->assertGreaterThan(0, $result->estimatedCost());

        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://api.anthropic.com/v1/messages'
                && $request->header('x-api-key') === ['test-key']
                && $request->header('anthropic-version') === ['2026-10-21']
                && $request->header('anthropic-beta') === ['server-side-fallback-2026-07-01']
                && $request['model'] === 'claude-opus-5-5'
                && $request['fallbacks'] === 'default'
                && $request['output_config']['format']['type'] === 'json_schema'
                && ! isset($request['thinking'])
                && str_contains($request['messages'][0]['content'], '<fact>Posidonia is endemic</fact>');
        });
    }

    public function test_anthropic_driver_retries_rate_limits_and_reports_refusals(): void
    {
        config(['fakkroun.ai.api_key' => 'test-key']);
        Sleep::fake();
        Http::fakeSequence('api.anthropic.com/*')
            ->push(['error' => ['type' => 'rate_limit_error']], 429, ['retry-after' => '1'])
            ->push($this->anthropicReply([$this->item('Retried question about the sea?')]));

        $this->assertCount(1, (new AnthropicQuestionGenerator)->generate(new GenerationRequest(1, 'en', 'easy', 'Blue Economy'))->items);

        Http::fake(['api.anthropic.com/*' => Http::response($this->anthropicReply([], 'refusal'))]);
        $this->expectException(GenerationFailed::class);
        (new AnthropicQuestionGenerator)->generate(new GenerationRequest(1, 'en', 'easy', 'Blue Economy'));
    }

    public function test_client_errors_are_not_retried(): void
    {
        config(['fakkroun.ai.api_key' => 'bad']);
        Http::fake(['api.anthropic.com/*' => Http::response(['error' => ['type' => 'authentication_error']], 401)]);

        try {
            (new AnthropicQuestionGenerator)->generate(new GenerationRequest(1, 'en', 'easy', 'Blue Economy'));
            $this->fail('Expected GenerationFailed.');
        } catch (GenerationFailed) {
        }

        Http::assertSentCount(1);
    }

    public function test_batches_store_valid_items_as_ai_drafts_and_report_the_rest(): void
    {
        FakeQuestionGenerator::$items = [
            $this->item('Which seagrass grows only in the Mediterranean Sea?'),
            $this->item('Which seagrass grows only in the Mediterranean Sea?'),
            $this->item('Only three answers for this question here?', choices: 3),
        ];

        $batch = app(GenerationService::class)->start($this->admin, [
            'category_id' => $this->category->id, 'difficulty' => 'medium', 'language' => 'en', 'count' => 3,
            'require_explanation' => true, 'require_reference' => false,
        ])->fresh();

        $this->assertSame('completed', $batch->status);
        $this->assertSame(1, $batch->accepted_count);
        $this->assertSame(1, $batch->duplicate_count);
        $this->assertSame(1, $batch->rejected_count);
        $this->assertGreaterThan(0, $batch->estimated_cost);

        $question = Question::query()->firstOrFail();
        $this->assertSame(QuestionSource::Ai, $question->source);
        $this->assertSame(QuestionStatus::Draft, $question->status);
        $this->assertSame('Zostera marina', $question->originTranslation()->choiceText($question->correct_choice_id));
        $this->assertSame([], $question->poolEntries()->pluck('id')->all());
    }

    public function test_batches_stop_at_the_cost_ceiling(): void
    {
        config(['fakkroun.ai.batch_cost_ceiling' => 0.000001, 'fakkroun.ai.chunk_size' => 2]);

        $batch = app(GenerationService::class)->start($this->admin, [
            'category_id' => $this->category->id, 'difficulty' => 'easy', 'language' => 'en', 'count' => 6,
        ])->fresh();

        $this->assertSame('stopped', $batch->status);
        $this->assertSame(2, $batch->generated_count);
    }

    public function test_ai_is_hidden_and_refused_without_a_driver(): void
    {
        config(['fakkroun.ai.driver' => 'none']);

        $this->assertFalse(app(QuestionDrafter::class)->enabled());
        $this->expectException(GenerationFailed::class);
        app(GenerationService::class)->start($this->admin, ['category_id' => $this->category->id, 'difficulty' => 'easy', 'language' => 'en', 'count' => 1]);
    }

    public function test_players_draft_with_ai_on_the_community_page_and_are_rate_limited(): void
    {
        config(['fakkroun.ai.drafts_per_hour' => 1]);
        $player = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Player->value));

        Livewire::actingAs($player)->test('pages::questions.submit')
            ->set('aiTopic', 'Seagrass')
            ->set('aiFact', 'Posidonia grows only in the Mediterranean')
            ->call('draftWithAi')
            ->assertHasNoErrors()
            ->assertSet('choices', ['Answer 1A', 'Answer 1B', 'Answer 1C', 'Answer 1D'])
            ->assertSet('correct', '1')
            ->call('draftWithAi')
            ->assertHasErrors('aiTopic');

        $this->assertSame(0, Question::count());
        $this->assertSame(1, AiGenerationBatch::query()->where('kind', 'draft')->count());
    }

    public function test_admin_ai_page_renders_and_starts_a_batch(): void
    {
        $this->actingAs($this->admin)->get(route('admin.ai'))->assertOk();

        Livewire::actingAs($this->admin)->test('pages::admin.ai')
            ->set('categoryId', (string) $this->category->id)
            ->set('count', 2)
            ->set('requireReference', false)
            ->call('start');

        $this->assertSame(2, Question::query()->where('source', 'ai')->count());
    }
}

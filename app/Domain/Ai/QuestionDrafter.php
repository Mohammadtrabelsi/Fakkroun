<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Contracts\QuestionGenerator;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

/**
 * "Draft with AI" for community submissions (design brief): turns a topic
 * and a fact into a question draft the player edits. It never saves or
 * publishes anything; the player submits it through normal moderation.
 */
class QuestionDrafter
{
    public function __construct(
        private readonly QuestionGenerator $generator,
        private readonly AiGenerationBatchService $aiBatches,
    ) {}

    public function enabled(): bool
    {
        $driver = (string) config('fakkroun.ai.driver', 'none');

        return $driver === 'fake' || ($driver === 'anthropic' && filled(config('fakkroun.ai.api_key')));
    }

    /**
     * @return array{body: string, choices: array<int, string>, correct: int, explanation: string|null, reference_url: string|null}
     */
    public function draft(string $topic, string $fact, string $language, string $difficulty, User $user): array
    {
        if (! $this->enabled()) {
            throw new GenerationFailed(__('ai.errors.disabled'));
        }

        $key = 'ai-drafts:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, (int) config('fakkroun.ai.drafts_per_hour', 10))) {
            throw new GenerationFailed(__('ai.errors.too_many_drafts'));
        }

        RateLimiter::hit($key, 3600);

        $request = new GenerationRequest(1, $language, $difficulty, $topic, topic: $topic, fact: $fact);
        $result = $this->generator->generate($request);

        $this->record($user, $request, $result);

        $item = $result->items[0] ?? null;
        $choices = array_values(array_map('strval', (array) ($item['choices'] ?? [])));

        if ($item === null || count($choices) !== 4) {
            throw new GenerationFailed(__('ai.errors.malformed'));
        }

        return [
            'body' => trim((string) ($item['question'] ?? '')),
            'choices' => $choices,
            'correct' => max(0, min(3, (int) ($item['correct_index'] ?? 0))),
            'explanation' => filled($item['explanation'] ?? null) ? (string) $item['explanation'] : null,
            'reference_url' => filled($item['reference_url'] ?? null) ? (string) $item['reference_url'] : null,
        ];
    }

    /**
     * Drafts are logged like batches so usage and cost stay visible to admins.
     */
    private function record(User $user, GenerationRequest $request, GenerationResult $result): void
    {
        $batch = $this->aiBatches->query()->create([
            'user_id' => $user->id,
            'kind' => 'draft',
            'provider' => $this->generator->provider(),
            'model' => $result->servedModel ?? $this->generator->model(),
            'prompt_version' => (string) config('fakkroun.ai.prompt_version'),
            'parameters' => ['language' => $request->language, 'difficulty' => $request->difficulty, 'topic' => $request->topic],
            'status' => 'completed',
            'requested_count' => 1,
        ]);

        $batch->forceFill([
            'generated_count' => count($result->items),
            'input_tokens' => $result->inputTokens,
            'output_tokens' => $result->outputTokens,
            'estimated_cost' => $result->estimatedCost(),
            'started_at' => now(),
            'finished_at' => now(),
        ])->save();
    }
}

<?php

namespace App\Domain\Ai\Drivers;

use App\Domain\Ai\Contracts\QuestionGenerator;
use App\Domain\Ai\GenerationRequest;
use App\Domain\Ai\GenerationResult;

/**
 * Deterministic generator for tests and local development
 * (FAKKROUN_AI_DRIVER=fake). Produces clearly labelled sample drafts.
 */
class FakeQuestionGenerator implements QuestionGenerator
{
    /** @var array<int, array<string, mixed>>|null fixed items to return (tests) */
    public static ?array $items = null;

    public function provider(): string
    {
        return 'fake';
    }

    public function model(): string
    {
        return 'fake-model';
    }

    public function generate(GenerationRequest $request): GenerationResult
    {
        $items = self::$items ?? array_map(fn (int $i) => [
            'question' => "[Sample] {$request->category} question {$i} about ".($request->topic ?: 'the sea').'?',
            'choices' => ["Answer {$i}A", "Answer {$i}B", "Answer {$i}C", "Answer {$i}D"],
            'correct_index' => $i % 4,
            'explanation' => 'Sample explanation.',
            'reference_url' => 'https://example.org/sample-'.$i,
        ], range(1, $request->count));

        return new GenerationResult(array_slice($items, 0, $request->count), 100 * $request->count, 200 * $request->count, 'fake-model');
    }
}

<?php

namespace App\Domain\Ai;

final class GenerationResult
{
    /**
     * @param  array<int, array<string, mixed>>  $items  raw drafted questions:
     *                                                   question, choices[4], correct_index, explanation, reference_url
     */
    public function __construct(
        public readonly array $items,
        public readonly int $inputTokens = 0,
        public readonly int $outputTokens = 0,
        public readonly ?string $servedModel = null,
    ) {}

    public function estimatedCost(): float
    {
        return round(
            $this->inputTokens / 1_000_000 * (float) config('fakkroun.ai.price_input_per_mtok', 0)
            + $this->outputTokens / 1_000_000 * (float) config('fakkroun.ai.price_output_per_mtok', 0),
            4,
        );
    }
}

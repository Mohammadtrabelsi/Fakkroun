<?php

namespace App\Domain\Ai;

/** Notes from an AI review of a campaign. Advisory only. */
final readonly class AdReviewResult
{
    /**
     * @param  array<int, array{principle: string, creative: int, explanation: string}>  $concerns
     */
    public function __construct(
        public array $concerns,
        public string $summary,
        public ?string $model = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['concerns' => $this->concerns, 'summary' => $this->summary, 'model' => $this->model, 'reviewed_at' => now()->toIso8601String()];
    }
}

<?php

namespace App\Domain\Ai;

/**
 * What to draft. Immutable value object passed to a QuestionGenerator.
 */
final class GenerationRequest
{
    public function __construct(
        public readonly int $count,
        public readonly string $language,
        public readonly string $difficulty,
        public readonly string $category,
        public readonly ?string $subcategory = null,
        public readonly ?string $audience = null,
        public readonly ?string $topic = null,
        public readonly ?string $fact = null,
        public readonly bool $requireExplanation = true,
        public readonly bool $requireReference = false,
        /** @var array<int, string> question texts to avoid repeating */
        public readonly array $avoid = [],
    ) {}
}

<?php

namespace App\Domain\Questions\Contracts;

/**
 * Finds questions whose text resembles the given one. Bound in
 * AppServiceProvider; swap the implementation (e.g. embeddings) without
 * touching the callers.
 */
interface SimilarQuestionFinder
{
    /**
     * @return array<int, float> question id => similarity (0..1), best first
     */
    public function find(string $language, string $body, float $threshold, ?int $exceptQuestionId = null): array;
}

<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\GenerationFailed;
use App\Domain\Ai\GenerationRequest;
use App\Domain\Ai\GenerationResult;

/**
 * Provider abstraction for AI question drafting (spec §10.3). Business logic
 * depends on this interface only; drivers are chosen by fakkroun.ai.driver.
 */
interface QuestionGenerator
{
    public function provider(): string;

    public function model(): string;

    /**
     * Drafts up to $request->count questions. Never throws for content
     * problems: those come back as items for validation to reject.
     *
     * @throws GenerationFailed on transport, auth, refusal or truncation errors
     */
    public function generate(GenerationRequest $request): GenerationResult;
}

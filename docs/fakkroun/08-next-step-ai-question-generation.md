# Next step 08: AI-assisted question generation (Phase 4)

> **Priority:** after the MVP is verified · **Effort:** large · **Spec:** §10, §8, §21
> **Depends on:** 03, 06
>
> **Update:** implemented in `claude/fakkroun-next-steps` (single drafts, batch generation, cost ceiling, provenance). Not yet executed; see [`14`](14-final-report.md).

## Principles

- AI **drafts**, people **decide**. Generated questions enter `draft` or `pending`, are never auto-published by default, and keep `source = ai` with a visible provenance badge.
- Factual categories (`requires_reference`) require a verifiable reference **before approval**, which `QuestionValidator::assertApprovable` already enforces.
- The provider is behind an abstraction, so business logic never depends on one vendor.

## Design

- `App\Domain\Ai\Contracts\QuestionGenerator::generate(GenerationRequest): GenerationResult`.
  Drivers: `AnthropicQuestionGenerator` (HTTP Messages API with structured JSON output; model from `.env`), and a `FakeQuestionGenerator` for tests.
- Tables (assessment §5.5):
  - `ai_generation_batches`: requester, provider, model, prompt version, parameters, counts, tokens, estimated cost, status;
  - `ai_generation_items`: raw output, validation result, `question_id`, duplicate-of.
- `GenerateQuestionBatch` job: chunks of e.g. 20 questions per call, retries with backoff on 429/5xx, a per-provider rate limiter, and a daily cost ceiling (setting).
- Pipeline per item: JSON schema check → `QuestionService::inspect()` (structure, warnings, exact and near duplicates) → answer-consistency check (a second, cheaper pass asking the model to answer its own question blind; flag disagreement) → `QuestionService::create(source: Ai)`.
- **Semantic and cross-language duplicates:** an embedding-based `SimilarQuestionFinder` (bind it in `AppServiceProvider`), with vectors stored per translation. This replaces `TokenOverlapFinder` for AI batches.
- Optional machine translation of approved questions into the other languages (`is_machine_translated = true`), always reviewed by a moderator.

## Admin screen

Category, subcategory, difficulty, language, count, audience, explanation and reference requirements → estimate → start → batch page with items, statuses and cost.

## Done when

- A 200-question batch runs on the queue within the configured cost ceiling. Every item ends as a reviewable draft or a recorded rejection with a reason.
- Tests run entirely against the fake driver.

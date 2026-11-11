<?php

namespace App\Domain\Ai;

use App\Domain\Ai\Contracts\QuestionGenerator;
use App\Domain\Ai\Jobs\GenerateQuestionBatch;
use App\Domain\Ai\Models\AiGenerationBatch;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionService;
use App\Models\User;
use App\Services\CategoryService;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Batch generation for administrators (spec §10.1–10.2): configure, queue,
 * generate in chunks, validate every item through QuestionService (structure,
 * quality warnings, exact duplicates), store accepted ones as AI drafts.
 */
class GenerationService
{
    public function __construct(
        private readonly QuestionGenerator $generator,
        private readonly QuestionService $questions,
        private readonly QuestionDrafter $drafter,
        private readonly AiGenerationBatchService $aiBatches,
        private readonly CategoryService $categories,
        private readonly AiGenerationItemService $aiItems,
    ) {}

    /**
     * @param  array<string, mixed>  $input  category_id, subcategory_id?, difficulty, language, count,
     *                                       audience?, topic?, require_explanation, require_reference
     */
    public function start(User $admin, array $input): AiGenerationBatch
    {
        if (! $this->drafter->enabled()) {
            throw new GenerationFailed(__('ai.errors.disabled'));
        }

        $validated = Validator::make($input, [
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->whereNull('parent_id')->whereNull('deleted_at')],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'difficulty' => ['required', Rule::in(['easy', 'medium', 'hard'])],
            'language' => ['required', Rule::in(array_keys((array) config('app.available_locales')))],
            'count' => ['required', 'integer', 'min:1', 'max:'.(int) config('fakkroun.ai.batch_max_questions', 1000)],
            'audience' => ['nullable', 'string', 'max:120'],
            'topic' => ['nullable', 'string', 'max:200'],
            'require_explanation' => ['boolean'],
            'require_reference' => ['boolean'],
        ])->validate();

        $batch = $this->aiBatches->query()->create([
            'user_id' => $admin->id,
            'kind' => 'batch',
            'provider' => $this->generator->provider(),
            'model' => $this->generator->model(),
            'prompt_version' => (string) config('fakkroun.ai.prompt_version'),
            'parameters' => $validated,
            'status' => 'pending',
            'requested_count' => (int) $validated['count'],
        ]);

        GenerateQuestionBatch::dispatch($batch->id);

        return $batch;
    }

    /**
     * Runs a batch chunk by chunk. Stops early at the cost ceiling.
     */
    public function process(AiGenerationBatch $batch): AiGenerationBatch
    {
        if ($batch->isFinished()) {
            return $batch;
        }

        $batch->forceFill(['status' => 'running', 'started_at' => now()])->save();
        $params = $batch->parameters;
        $category = $this->categories->query()->findOrFail($params['category_id']);
        $subcategory = filled($params['subcategory_id'] ?? null) ? $this->categories->query()->find($params['subcategory_id']) : null;
        $chunk = max(1, (int) config('fakkroun.ai.chunk_size', 10));
        $ceiling = (float) config('fakkroun.ai.batch_cost_ceiling', 20.0);
        $status = config('fakkroun.ai.initial_status') === 'pending' ? QuestionStatus::Pending : QuestionStatus::Draft;
        $author = $batch->user;

        try {
            while ($batch->generated_count < $batch->requested_count) {
                if ($batch->estimated_cost >= $ceiling) {
                    $batch->forceFill(['status' => 'stopped', 'error' => __('ai.errors.cost_ceiling', ['ceiling' => $ceiling])])->save();
                    break;
                }

                $want = min($chunk, $batch->requested_count - $batch->generated_count);
                $result = $this->generator->generate(new GenerationRequest(
                    $want,
                    $params['language'],
                    $params['difficulty'],
                    $category->getTranslation('name', 'en', false) ?: $category->name,
                    $subcategory?->getTranslation('name', 'en', false),
                    $params['audience'] ?? null,
                    $params['topic'] ?? null,
                    requireExplanation: (bool) ($params['require_explanation'] ?? true),
                    requireReference: (bool) ($params['require_reference'] ?? $category->requires_reference),
                    avoid: $this->recentTexts($batch),
                ));

                foreach (array_slice($result->items, 0, $want) as $item) {
                    $this->store($batch, $item, $params, $subcategory?->id, $author, $status);
                }

                $batch->forceFill([
                    // Count what was asked for, so a short reply cannot loop forever.
                    'generated_count' => $batch->generated_count + $want,
                    'input_tokens' => $batch->input_tokens + $result->inputTokens,
                    'output_tokens' => $batch->output_tokens + $result->outputTokens,
                    'estimated_cost' => round($batch->estimated_cost + $result->estimatedCost(), 4),
                    'model' => $result->servedModel ?? $batch->model,
                ])->save();
            }

            if ($batch->status === 'running') {
                $batch->forceFill(['status' => 'completed'])->save();
            }
        } catch (Throwable $e) {
            $batch->forceFill(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 1000)])->save();
        }

        $batch->forceFill(['finished_at' => now()])->save();

        return $batch;
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $params
     */
    private function store(AiGenerationBatch $batch, array $item, array $params, ?int $subcategoryId, ?User $author, QuestionStatus $status): void
    {
        $data = [
            'category_id' => (int) $params['category_id'],
            'subcategory_id' => $subcategoryId,
            'difficulty' => $params['difficulty'],
            'language' => $params['language'],
            'body' => trim((string) ($item['question'] ?? '')),
            'choices' => array_values(array_map('strval', (array) ($item['choices'] ?? []))),
            'correct' => (int) ($item['correct_index'] ?? -1),
            'explanation' => filled($item['explanation'] ?? null) ? (string) $item['explanation'] : null,
            'reference_url' => filled($item['reference_url'] ?? null) ? (string) $item['reference_url'] : null,
        ];

        try {
            $question = $this->questions->create($data, $author, QuestionSource::Ai, $status);
            $batch->accepted_count++;
            $this->aiItems->query()->create(['batch_id' => $batch->id, 'payload' => $item, 'outcome' => 'accepted', 'question_id' => $question->id]);
        } catch (ValidationException $e) {
            $messages = array_values($e->validator->errors()->all());
            $duplicate = $messages === [__('questions.errors.duplicate')];
            $duplicate ? $batch->duplicate_count++ : $batch->rejected_count++;
            $this->aiItems->query()->create(['batch_id' => $batch->id, 'payload' => $item, 'outcome' => $duplicate ? 'duplicate' : 'invalid', 'messages' => $messages]);
        }

        $batch->save();
    }

    /**
     * Texts accepted earlier in this batch, so later chunks don't repeat them.
     *
     * @return array<int, string>
     */
    private function recentTexts(AiGenerationBatch $batch): array
    {
        $ids = $batch->items()->whereNotNull('question_id')->latest('id')->limit(30)->pluck('question_id');

        return $this->questions->query()->whereIn('id', $ids)->with('translations')->get()->map(fn (Question $q) => $q->label())->all();
    }
}

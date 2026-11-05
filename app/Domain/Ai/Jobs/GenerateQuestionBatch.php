<?php

namespace App\Domain\Ai\Jobs;

use App\Domain\Ai\GenerationService;
use App\Domain\Ai\Models\AiGenerationBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Runs an AI generation batch off the request. HTTP retries happen inside the
 * driver; the job itself is not retried, so a batch is never billed twice.
 */
class GenerateQuestionBatch implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public readonly int $batchId) {}

    public function uniqueId(): string
    {
        return 'ai-batch-'.$this->batchId;
    }

    public function handle(GenerationService $generation): void
    {
        if ($batch = AiGenerationBatch::query()->find($this->batchId)) {
            $generation->process($batch);
        }
    }
}

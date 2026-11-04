<?php

namespace App\Domain\Questions\Jobs;

use App\Domain\Questions\Imports\QuestionImportService;
use App\Domain\Questions\Models\QuestionImportBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Processes an uploaded question file off the request (spec §11, §27).
 * Not retried: a half-processed file would otherwise create duplicates;
 * the batch is marked failed and the admin can upload again.
 */
class ProcessQuestionImport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(public readonly int $batchId) {}

    public function uniqueId(): string
    {
        return 'question-import-'.$this->batchId;
    }

    public function handle(QuestionImportService $imports): void
    {
        $batch = QuestionImportBatch::query()->find($this->batchId);

        if ($batch !== null) {
            $imports->process($batch);
        }
    }

    public function failed(?Throwable $exception): void
    {
        QuestionImportBatch::query()
            ->whereKey($this->batchId)
            ->whereNotIn('status', [QuestionImportBatch::STATUS_COMPLETED])
            ->update([
                'status' => QuestionImportBatch::STATUS_FAILED,
                'error' => mb_substr((string) $exception?->getMessage(), 0, 1000),
                'finished_at' => now(),
            ]);
    }
}

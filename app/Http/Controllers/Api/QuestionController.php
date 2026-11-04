<?php

namespace App\Http\Controllers\Api;

use App\Domain\Questions\Enums\ReportReason;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\ReportService;
use App\Domain\Questions\SubmissionService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class QuestionController extends Controller
{
    public function __construct(
        private readonly SubmissionService $submissions,
        private readonly ReportService $reports,
    ) {}

    /**
     * SubmissionService::mine
     */
    public function mySubmissions(Request $request): JsonResponse
    {
        return response()->json($this->submissions->mine($request->user(), (int) $request->integer('per_page', 10) ?: 10));
    }

    /**
     * SubmissionService::submit — same content shape as the web form.
     */
    public function submit(Request $request): JsonResponse
    {
        $data = $request->only(['category_id', 'subcategory_id', 'difficulty', 'language', 'body', 'choices', 'correct', 'explanation', 'reference_url', 'reference_text']);
        $data['language'] ??= $request->user()->preferredQuestionLocale();

        $question = $this->submissions->submit($request->user(), $data);

        return response()->json([
            'uuid' => $question->uuid,
            'status' => $question->status->value,
        ], 201);
    }

    /**
     * ReportService::report
     */
    public function report(Request $request, Question $question): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', Rule::in(ReportReason::values())],
            'details' => ['nullable', 'string', 'max:2000'],
            'language' => ['nullable', 'string', 'max:8'],
        ]);

        $report = $this->reports->report($request->user(), $question, ReportReason::from($validated['reason']), $validated['details'] ?? null, $validated['language'] ?? null);

        return response()->json(['id' => $report->id, 'status' => $report->status->value], 201);
    }
}

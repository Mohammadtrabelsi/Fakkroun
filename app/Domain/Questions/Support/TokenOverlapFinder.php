<?php

namespace App\Domain\Questions\Support;

use App\Domain\Questions\Contracts\SimilarQuestionFinder;
use App\Domain\Questions\DuplicateDetector;
use App\Domain\Questions\QuestionTranslationService;

/**
 * Default near-duplicate finder: narrows candidates with the text's three
 * longest words (LIKE), then scores word overlap. Bounded by
 * fakkroun.questions.similarity_candidates rows per check, so it is meant
 * for moderation-time checks, not for gameplay paths.
 */
class TokenOverlapFinder implements SimilarQuestionFinder
{
    public function find(string $language, string $body, float $threshold, ?int $exceptQuestionId = null): array
    {
        $detector = app(DuplicateDetector::class);
        $tokens = $detector->tokens($body);

        if ($tokens === []) {
            return [];
        }

        usort($tokens, fn (string $a, string $b) => mb_strlen($b) <=> mb_strlen($a));
        $probes = array_slice($tokens, 0, 3);

        $candidates = app(QuestionTranslationService::class)->query()
            ->select(['question_id', 'body'])
            ->where('language', $language)
            ->when($exceptQuestionId, fn ($query) => $query->where('question_id', '!=', $exceptQuestionId))
            ->where(function ($query) use ($probes): void {
                foreach ($probes as $probe) {
                    $query->orWhere('body', 'like', '%'.addcslashes($probe, '%_\\').'%');
                }
            })
            ->whereHas('question')
            ->limit((int) config('fakkroun.questions.similarity_candidates', 50))
            ->get();

        $scores = [];

        foreach ($candidates as $candidate) {
            $score = $detector->similarity($body, $candidate->body);

            if ($score >= $threshold) {
                $scores[(int) $candidate->question_id] = max($scores[(int) $candidate->question_id] ?? 0, round($score, 3));
            }
        }

        arsort($scores);

        return $scores;
    }
}

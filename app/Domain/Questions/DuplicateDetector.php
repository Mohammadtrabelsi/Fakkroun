<?php

namespace App\Domain\Questions;

use App\Domain\Questions\Contracts\SimilarQuestionFinder;

/**
 * Duplicate detection (spec §8).
 *
 *  - exact: sha256 of the normalised question text plus its sorted answers,
 *    compared within one language through an index;
 *  - similar: a SimilarQuestionFinder (token overlap by default; Phase 4 can
 *    bind an embedding-based finder for semantic and cross-language matches).
 */
class DuplicateDetector
{
    public function __construct(
        private readonly SimilarQuestionFinder $finder,
        private readonly QuestionTranslationService $questionTranslations,
    ) {}

    /**
     * @param  array<int, string>  $choices
     */
    public function hash(string $body, array $choices): string
    {
        $answers = array_map(fn (string $choice) => $this->normalize($choice), $choices);
        sort($answers);

        return hash('sha256', $this->normalize($body).'|'.implode('|', $answers));
    }

    /**
     * Ids of other questions with exactly the same text and answers in this language.
     *
     * @return array<int, int>
     */
    public function exactMatches(string $language, string $hash, ?int $exceptQuestionId = null): array
    {
        return $this->questionTranslations->query()
            ->where('language', $language)
            ->where('content_hash', $hash)
            ->when($exceptQuestionId, fn ($query) => $query->where('question_id', '!=', $exceptQuestionId))
            ->whereHas('question')
            ->pluck('question_id')
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Possible near-duplicates: question id => similarity (0..1), best first.
     *
     * @return array<int, float>
     */
    public function similar(string $language, string $body, ?int $exceptQuestionId = null): array
    {
        return $this->finder->find(
            $language,
            $body,
            (float) config('fakkroun.questions.similarity_threshold', 0.8),
            $exceptQuestionId,
        );
    }

    /**
     * Lower-case, strip Arabic diacritics and tatweel, unify Arabic letter
     * variants, drop punctuation and collapse whitespace, so trivial
     * differences do not hide a duplicate.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        // Arabic tashkeel (U+064B–U+065F, U+0670) and tatweel (U+0640).
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text);
        // Alef variants → bare alef, teh marbuta → heh, alef maqsura → yeh.
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);

        if (class_exists(\Normalizer::class)) {
            // Latin accents: é → e (decompose, then drop combining marks).
            $text = (string) \Normalizer::normalize($text, \Normalizer::FORM_D);
            $text = (string) preg_replace('/\p{Mn}+/u', '', $text);
        }

        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Distinct words of at least three characters.
     *
     * @return array<int, string>
     */
    public function tokens(string $text): array
    {
        return array_values(array_unique(array_filter(
            explode(' ', $this->normalize($text)),
            fn (string $word) => mb_strlen($word) >= 3,
        )));
    }

    /**
     * Jaccard similarity of the two texts' word sets (0..1).
     */
    public function similarity(string $a, string $b): float
    {
        $left = $this->tokens($a);
        $right = $this->tokens($b);

        if ($left === [] || $right === []) {
            return $this->normalize($a) === $this->normalize($b) ? 1.0 : 0.0;
        }

        $intersection = count(array_intersect($left, $right));
        $union = count(array_unique([...$left, ...$right]));

        return $union === 0 ? 0.0 : $intersection / $union;
    }
}

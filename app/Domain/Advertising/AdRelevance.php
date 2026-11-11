<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Creative;
use App\Services\PlatformSettings;

/**
 * Contextual relevance of a creative for the question just answered
 * (story 16 S1). Context only (topic, language, words of the question):
 * never anything about the player.
 *
 * Score, higher is better (weights are platform settings, defaults shown):
 *   +4  weight_topic: the campaign explicitly targets the question's category
 *       or subcategory (room-level placements: one of the room's categories)
 *   +1  weight_language: the campaign explicitly targets the room language
 *   +1  weight_language: the creative is written in the room language
 *   +2  weight_keyword: per creative keyword found in the question or its
 *       category names (at most max_keyword_hits counted)
 * A score of 0 means "untargeted": eligible only when advertising.allow_untargeted is on.
 */
class AdRelevance
{
    public function __construct(private readonly PlatformSettings $settings) {}

    /**
     * @param  array{category_ids: array<int, int>, language: string, text: string}  $context
     */
    public function score(Campaign $campaign, Creative $creative, array $context): int
    {
        $categories = array_map('intval', (array) ($campaign->target_category_ids ?? []));
        $languages = (array) ($campaign->target_languages ?? []);

        $w = $this->settings->all()['advertising'];
        $score = 0;

        if ($categories !== [] && array_intersect($categories, array_map('intval', $context['category_ids'])) !== []) {
            $score += (int) $w['weight_topic'];
        }

        if ($languages !== [] && in_array($context['language'], $languages, true)) {
            $score += (int) $w['weight_language'];
        }

        if ($creative->language !== null && $creative->language === $context['language']) {
            $score += (int) $w['weight_language'];
        }

        return $score + (int) $w['weight_keyword'] * min((int) $w['max_keyword_hits'], $this->keywordHits((array) ($creative->keywords ?? []), $context['text']));
    }

    /**
     * @param  array<int, string>  $keywords
     */
    public function keywordHits(array $keywords, string $text): int
    {
        $haystack = $this->normalize($text);

        return count(array_filter($keywords, function ($keyword) use ($haystack): bool {
            $needle = $this->normalize((string) $keyword);

            return mb_strlen($needle) >= 2 && str_contains($haystack, $needle);
        }));
    }

    /**
     * Lower case, Arabic diacritics and tatweel removed, spaces collapsed.
     */
    public function normalize(string $text): string
    {
        $text = mb_strtolower($text);
        $text = (string) preg_replace('/[\x{064B}-\x{0652}\x{0640}]/u', '', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}

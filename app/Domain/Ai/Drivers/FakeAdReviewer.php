<?php

namespace App\Domain\Ai\Drivers;

use App\Domain\Ai\AdReviewResult;
use App\Domain\Ai\Contracts\AdReviewer;

/** Test and local driver: flags creatives whose text contains a word from $flagWords. */
class FakeAdReviewer implements AdReviewer
{
    /** @var array<int, string> */
    public static array $flagWords = [];

    public function review(array $campaign): AdReviewResult
    {
        $concerns = [];

        foreach ($campaign['creatives'] as $index => $creative) {
            $text = mb_strtolower($creative['headline'].' '.($creative['body'] ?? ''));

            foreach (self::$flagWords as $word) {
                if (str_contains($text, mb_strtolower($word))) {
                    $concerns[] = ['principle' => 'halal', 'creative' => $index + 1, 'explanation' => "Mentions “{$word}”."];
                }
            }
        }

        return new AdReviewResult($concerns, $concerns === [] ? 'No concerns found.' : count($concerns).' possible concern(s).', 'fake');
    }
}

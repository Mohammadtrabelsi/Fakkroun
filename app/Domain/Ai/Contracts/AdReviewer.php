<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\AdReviewResult;

/**
 * Checks an advertising campaign against the published advertising policy
 * (story 16 S8). It only ever produces NOTES for a human reviewer: it never
 * approves or rejects anything.
 */
interface AdReviewer
{
    /**
     * @param  array{advertiser: string, campaign: string, creatives: array<int, array{headline: string, body: ?string, link_url: ?string, keywords: array<int, string>}>}  $campaign
     */
    public function review(array $campaign): AdReviewResult;
}

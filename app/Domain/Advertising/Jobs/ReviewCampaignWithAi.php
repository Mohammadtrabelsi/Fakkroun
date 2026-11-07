<?php

namespace App\Domain\Advertising\Jobs;

use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Ai\Contracts\AdReviewer;
use App\Domain\Ai\GenerationFailed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Story 16 S8: when a campaign is submitted, ask the AI for notes against the
 * advertising policy and store them for the human reviewer. Never changes
 * the campaign's status: a person always decides.
 */
class ReviewCampaignWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public int $campaignId) {}

    public function handle(AdReviewer $reviewer): void
    {
        $campaign = Campaign::query()->with(['advertiser', 'creatives'])->find($this->campaignId);

        if ($campaign === null || $campaign->status !== CampaignStatus::PendingReview) {
            return;
        }

        try {
            $result = $reviewer->review([
                'advertiser' => $campaign->advertiser->name,
                'campaign' => $campaign->name,
                'creatives' => $campaign->creatives->map(fn ($c) => [
                    'headline' => $c->headline,
                    'body' => $c->body,
                    'link_url' => $c->link_url,
                    'keywords' => (array) ($c->keywords ?? []),
                ])->values()->all(),
            ])->toArray();
        } catch (GenerationFailed $e) {
            $result = ['error' => $e->getMessage(), 'concerns' => [], 'reviewed_at' => now()->toIso8601String()];
        }

        Campaign::query()->whereKey($campaign->id)->update(['ai_review' => json_encode($result)]);
    }
}

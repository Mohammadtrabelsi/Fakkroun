<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\Creative;
use App\Domain\Advertising\Notifications\AdvertiserNotice;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Campaign lifecycle: advertisers draft and submit; administrators review
 * every campaign and creative before it can run (spec §17, §21: AI may
 * assist, never approve).
 */
class CampaignService
{
    public function advertiserFor(User $user): ?Advertiser
    {
        return Advertiser::query()->where('user_id', $user->id)->first();
    }

    /**
     * @param  array<string, mixed>  $data  name, starts_at?, ends_at?, target_category_ids[], target_languages[], max_per_player_per_game?
     */
    public function create(Advertiser $advertiser, User $actor, array $data): Campaign
    {
        $this->authorizeOwner($advertiser, $actor);

        $validated = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'target_category_ids' => ['array'],
            'target_category_ids.*' => ['integer', Rule::exists('categories', 'id')],
            'target_languages' => ['array'],
            'target_languages.*' => [Rule::in(array_keys((array) config('app.available_locales')))],
            'max_per_player_per_game' => ['nullable', 'integer', 'min:1', 'max:20'],
        ])->validate();

        return Campaign::query()->create($validated + ['advertiser_id' => $advertiser->id, 'max_per_player_per_game' => $validated['max_per_player_per_game'] ?? 2]);
    }

    /**
     * @param  array<string, mixed>  $data  headline, body?, link_url?, alt_text?, language?, format?, keywords?[]
     */
    public function addCreative(Campaign $campaign, User $actor, array $data, ?UploadedFile $image = null): Creative
    {
        $this->authorizeOwner($campaign->advertiser, $actor);

        $validated = Validator::make($data + ['image' => $image], [
            'headline' => ['required', 'string', 'max:120'],
            'body' => ['nullable', 'string', 'max:280'],
            'link_url' => ['nullable', 'url:https', 'max:2048'],
            'alt_text' => ['nullable', 'string', 'max:200'],
            'language' => ['nullable', Rule::in(array_keys((array) config('app.available_locales')))],
            'format' => ['nullable', Rule::in(['banner', 'logo', 'post_game'])],
            'image' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            // Topic words matched against questions (story 16 S1).
            'keywords' => ['nullable', 'array', 'max:'.(int) app(\App\Services\PlatformSettings::class)->get('advertising.max_keywords_per_creative')],
            'keywords.*' => ['string', 'min:2', 'max:40'],
        ])->validate();

        unset($validated['image']);
        $creative = new Creative($validated + ['campaign_id' => $campaign->id, 'format' => $validated['format'] ?? 'banner']);
        $creative->image_path = $image?->storePublicly('ads', 'public');
        $creative->save();

        // A changed campaign goes back to review.
        if ($campaign->status === CampaignStatus::Active) {
            $this->setStatus($campaign, CampaignStatus::PendingReview);
        }

        return $creative;
    }

    public function submit(Campaign $campaign, User $actor): Campaign
    {
        $this->authorizeOwner($campaign->advertiser, $actor);
        $this->assertStatus($campaign, [CampaignStatus::Draft, CampaignStatus::Rejected, CampaignStatus::Paused]);

        if (! $campaign->creatives()->exists()) {
            throw new DomainActionException(__('advertising.errors.no_creative'));
        }

        $campaign = $this->setStatus($campaign, CampaignStatus::PendingReview);
        $campaign->forceFill(['ai_review' => null])->save();

        // Story 16 S8: AI notes for the reviewer, when AI is configured and the setting is on.
        if (app(\App\Domain\Ai\QuestionDrafter::class)->enabled() && app(\App\Services\PlatformSettings::class)->get('advertising.ai_review_enabled')) {
            \App\Domain\Advertising\Jobs\ReviewCampaignWithAi::dispatch($campaign->id);
        }

        return $campaign;
    }

    /**
     * $policyChecked: the reviewer confirms the campaign was checked against
     * the advertising policy (story 16 S13). Approval is refused without it.
     */
    public function approve(Campaign $campaign, User $admin, bool $policyChecked = false): Campaign
    {
        $this->authorizeAdmin($admin);
        $this->assertStatus($campaign, [CampaignStatus::PendingReview]);

        if (! $policyChecked) {
            throw new DomainActionException(__('advertising.errors.policy_unchecked'));
        }

        $campaign->forceFill(['policy_checked_at' => now(), 'policy_checked_by' => $admin->id]);

        $campaign->creatives()->where('status', 'pending')->update(['status' => 'approved']);
        $campaign->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => null]);
        $this->setStatus($campaign, CampaignStatus::Active);
        $campaign->advertiser->user?->notify(new AdvertiserNotice('activated', ['campaign' => $campaign->name]));

        return $campaign;
    }

    public function reject(Campaign $campaign, User $admin, string $note): Campaign
    {
        $this->authorizeAdmin($admin);
        $this->assertStatus($campaign, [CampaignStatus::PendingReview, CampaignStatus::Active, CampaignStatus::Paused]);

        if (trim($note) === '') {
            throw new DomainActionException(__('questions.errors.reason_required'));
        }

        $campaign->forceFill(['reviewed_by' => $admin->id, 'reviewed_at' => now(), 'review_note' => trim($note)]);

        return $this->setStatus($campaign, CampaignStatus::Rejected);
    }

    public function pause(Campaign $campaign, User $actor): Campaign
    {
        if (! $actor->can('manage advertising')) {
            $this->authorizeOwner($campaign->advertiser, $actor);
        }

        $this->assertStatus($campaign, [CampaignStatus::Active]);

        return $this->setStatus($campaign, CampaignStatus::Paused);
    }

    public function markExhausted(Campaign $campaign): void
    {
        if ($campaign->status === CampaignStatus::Active) {
            $this->setStatus($campaign, CampaignStatus::Exhausted);
            $campaign->advertiser->user?->notify(new AdvertiserNotice('exhausted', ['campaign' => $campaign->name]));
        }
    }

    /**
     * Exhausted campaigns come back once credits are added; ended ones complete.
     */
    public function refreshStatuses(): void
    {
        Campaign::query()->where('status', CampaignStatus::Active->value)->whereNotNull('ends_at')->where('ends_at', '<=', now())
            ->update(['status' => CampaignStatus::Completed->value]);
    }

    /**
     * @param  array<int, CampaignStatus>  $allowed
     */
    private function assertStatus(Campaign $campaign, array $allowed): void
    {
        if (! in_array($campaign->status, $allowed, true)) {
            throw new DomainActionException(__('advertising.errors.invalid_status'));
        }
    }

    private function setStatus(Campaign $campaign, CampaignStatus $status): Campaign
    {
        $campaign->status = $status;
        $campaign->save();

        return $campaign;
    }

    private function authorizeOwner(Advertiser $advertiser, User $actor): void
    {
        if ($advertiser->user_id !== $actor->id && ! $actor->can('manage advertising')) {
            throw new AuthorizationException;
        }

        // A suspended advertiser (third policy breach) can't change campaigns.
        if ($advertiser->status === 'suspended' && ! $actor->can('manage advertising')) {
            throw new DomainActionException(__('advertising.errors.advertiser_suspended'));
        }
    }

    private function authorizeAdmin(User $admin): void
    {
        if (! $admin->can('manage advertising')) {
            throw new AuthorizationException;
        }
    }
}

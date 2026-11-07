<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\PolicyStrike;
use App\Domain\Advertising\Notifications\AdvertiserNotice;
use App\Exceptions\DomainActionException;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * Advertising policy breaches (owner decision 2026-11-05):
 *   1st breach: written warning;
 *   2nd breach: the team calls the advertiser (admins record the call);
 *   3rd breach: the advertiser account is suspended.
 *
 * Every breach stops the campaign concerned. Unused credits are kept but
 * frozen while the account is suspended (running campaigns require an
 * active advertiser); they become usable again if an admin reactivates it.
 */
class PolicyEnforcementService
{
    public function __construct(
        private readonly AdvertiserService $advertisers,
        private readonly PolicyStrikeService $policyStrikes,
        private readonly CampaignService $campaigns,
    ) {}

    public const SUSPEND_AT = 3;

    public function recordBreach(Campaign $campaign, User $admin, string $note): PolicyStrike
    {
        $this->authorize($admin);

        if (trim($note) === '') {
            throw new DomainActionException(__('questions.errors.reason_required'));
        }

        $strike = DB::transaction(function () use ($campaign, $admin, $note): PolicyStrike {
            /** @var Advertiser $advertiser */
            $advertiser = $this->advertisers->query()->whereKey($campaign->advertiser_id)->lockForUpdate()->firstOrFail();
            $level = $this->policyStrikes->query()->where('advertiser_id', $advertiser->id)->count() + 1;

            $strike = $this->policyStrikes->query()->create([
                'advertiser_id' => $advertiser->id,
                'campaign_id' => $campaign->id,
                'level' => $level,
                'note' => trim($note),
                'created_by' => $admin->id,
            ]);

            $campaign->forceFill([
                'status' => CampaignStatus::Rejected,
                'reviewed_by' => $admin->id,
                'reviewed_at' => now(),
                'review_note' => trim($note),
            ])->save();

            if ($level >= self::SUSPEND_AT) {
                $advertiser->forceFill(['status' => 'suspended'])->save();
                $this->campaigns->query()->where('advertiser_id', $advertiser->id)
                    ->whereIn('status', [CampaignStatus::Active->value, CampaignStatus::PendingReview->value])
                    ->update(['status' => CampaignStatus::Paused->value]);
            }

            return $strike;
        });

        $event = match (true) {
            $strike->level >= self::SUSPEND_AT => 'policy_suspended',
            $strike->level === 2 => 'policy_call',
            default => 'policy_warning',
        };

        $campaign->advertiser->user?->notify(new AdvertiserNotice($event, ['campaign' => $campaign->name, 'reason' => $strike->note]));

        return $strike;
    }

    /** Records that the team made the second-breach call. */
    public function recordCall(PolicyStrike $strike, User $admin): PolicyStrike
    {
        $this->authorize($admin);

        if ($strike->called_at === null) {
            $strike->forceFill(['called_at' => now()])->save();
        }

        return $strike;
    }

    /** Lifts a suspension. Past strikes stay on record. */
    public function reactivate(Advertiser $advertiser, User $admin): Advertiser
    {
        $this->authorize($admin);
        $advertiser->forceFill(['status' => 'active'])->save();
        $advertiser->user?->notify(new AdvertiserNotice('policy_reactivated', []));

        return $advertiser;
    }

    /** Strikes still waiting for the second-breach call. */
    public function callsDue()
    {
        return $this->policyStrikes->query()->where('level', 2)->whereNull('called_at')->get();
    }

    private function authorize(User $admin): void
    {
        if (! $admin->can('manage advertising')) {
            throw new AuthorizationException;
        }
    }
}

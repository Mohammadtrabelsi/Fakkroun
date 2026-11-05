<?php

use App\Domain\Advertising\AdvertisingStats;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Enums\CampaignStatus;
use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\ReconciliationService;
use App\Domain\Advertising\WalletService;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\PlatformSettings;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** Advertising back office (spec §18–§21): advertisers, packages, credits, review, reconciliation. */
new #[Layout('layouts::app')] #[Title('Advertising')] class extends Component
{
    use InteractsWithToasts;

    public string $tab = 'review';

    // New advertiser
    public string $advEmail = '';

    public string $advName = '';

    public string $advType = 'company';

    public string $advWebsite = '';

    // Package form
    public ?int $packageId = null;

    public string $pkgName = '';

    public int $pkgImpressions = 0;

    public int $pkgSponsored = 0;

    public string $pkgPrice = '';

    public bool $pkgActive = true;

    // Purchase / adjustment
    public ?int $walletAdvertiser = null;

    public ?int $purchasePackage = null;

    public string $paymentReference = '';

    public string $adjustType = 'impression';

    public int $adjustQuantity = 0;

    public string $adjustNote = '';

    /** @var array<int, string> */
    public array $rejectNotes = [];

    public function mount(): void
    {
        $this->authorize('manage advertising');
    }

    #[Computed]
    public function advertisers()
    {
        return Advertiser::query()->with(['user', 'wallets'])->withCount('campaigns')->orderBy('name')->get();
    }

    #[Computed]
    public function packages()
    {
        return AdvertisingPackage::query()->orderBy('price_minor')->get();
    }

    #[Computed]
    public function pending()
    {
        return Campaign::query()->with(['advertiser', 'creatives'])
            ->whereIn('status', [CampaignStatus::PendingReview->value, CampaignStatus::Active->value, CampaignStatus::Paused->value, CampaignStatus::Exhausted->value])
            ->orderByRaw("case when status = 'pending_review' then 0 else 1 end")->latest('id')->limit(50)->get();
    }

    #[Computed]
    public function stats(): array
    {
        return app(AdvertisingStats::class)->platform();
    }

    #[Computed]
    public function reconciliation(): ?array
    {
        return app(ReconciliationService::class)->last();
    }

    public function createAdvertiser(): void
    {
        $data = $this->validate([
            'advEmail' => ['required', 'email', Rule::exists('users', 'email')],
            'advName' => ['required', 'string', 'max:120'],
            'advType' => ['required', Rule::in(['ngo', 'school', 'university', 'association', 'company', 'other'])],
            'advWebsite' => ['nullable', 'url:https', 'max:2048'],
        ]);

        $user = User::query()->where('email', $data['advEmail'])->firstOrFail();

        if (Advertiser::query()->where('user_id', $user->id)->exists()) {
            $this->addError('advEmail', __('advertising.errors.already_advertiser'));

            return;
        }

        Advertiser::query()->create(['user_id' => $user->id, 'name' => $data['advName'], 'type' => $data['advType'], 'contact_email' => $user->email, 'website' => $data['advWebsite'] ?: null]);
        $user->assignRole(UserRole::Advertiser->value);

        $this->reset(['advEmail', 'advName', 'advType', 'advWebsite']);
        unset($this->advertisers);
        $this->toast(__('advertising.advertiser_created'));
    }

    public function editPackage(int $id): void
    {
        $p = AdvertisingPackage::query()->findOrFail($id);
        $this->packageId = $p->id;
        $this->pkgName = $p->name;
        $this->pkgImpressions = $p->impression_credits;
        $this->pkgSponsored = $p->sponsored_question_credits;
        $this->pkgPrice = number_format($p->price_minor / 100, 2, '.', '');
        $this->pkgActive = $p->is_active;
    }

    public function savePackage(PlatformSettings $settings): void
    {
        $data = $this->validate([
            'pkgName' => ['required', 'string', 'max:120'],
            'pkgImpressions' => ['required', 'integer', 'min:0', 'max:100000000'],
            'pkgSponsored' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pkgPrice' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'pkgActive' => ['boolean'],
        ]);

        if ($data['pkgImpressions'] + $data['pkgSponsored'] === 0) {
            $this->addError('pkgImpressions', __('advertising.errors.empty_package'));

            return;
        }

        AdvertisingPackage::query()->updateOrCreate(['id' => $this->packageId], [
            'name' => $data['pkgName'],
            'impression_credits' => $data['pkgImpressions'],
            'sponsored_question_credits' => $data['pkgSponsored'],
            'price_minor' => (int) round(((float) $data['pkgPrice']) * 100),
            'currency' => (string) $settings->get('advertising.currency'),
            'is_active' => $data['pkgActive'],
        ]);

        $this->reset(['packageId', 'pkgName', 'pkgImpressions', 'pkgSponsored', 'pkgPrice', 'pkgActive']);
        unset($this->packages);
        $this->toast(__('advertising.package_saved'));
    }

    public function recordPurchase(WalletService $wallets): void
    {
        $this->validate([
            'walletAdvertiser' => ['required', Rule::exists('advertisers', 'id')],
            'purchasePackage' => ['required', Rule::exists('advertising_packages', 'id')],
            'paymentReference' => ['required', 'string', 'max:120'],
        ]);

        $this->attempt(fn () => $wallets->purchase(
            Advertiser::query()->findOrFail($this->walletAdvertiser),
            AdvertisingPackage::query()->findOrFail($this->purchasePackage),
            auth()->user(),
            trim($this->paymentReference),
        ), __('advertising.purchase_recorded'));

        $this->reset(['purchasePackage', 'paymentReference']);
        unset($this->advertisers);
    }

    public function adjustCredits(WalletService $wallets): void
    {
        $this->validate([
            'walletAdvertiser' => ['required', Rule::exists('advertisers', 'id')],
            'adjustType' => ['required', Rule::in(array_column(CreditType::cases(), 'value'))],
            'adjustQuantity' => ['required', 'integer', 'not_in:0'],
            'adjustNote' => ['required', 'string', 'max:500'],
        ]);

        $this->attempt(fn () => $wallets->adjust(
            Advertiser::query()->findOrFail($this->walletAdvertiser),
            CreditType::from($this->adjustType),
            $this->adjustQuantity,
            auth()->user(),
            $this->adjustNote,
        ), __('advertising.adjusted'));

        $this->reset(['adjustQuantity', 'adjustNote']);
        unset($this->advertisers);
    }

    public function approve(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->approve(Campaign::query()->findOrFail($id), auth()->user()), __('advertising.approved'));
        unset($this->pending);
    }

    public function reject(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->reject(Campaign::query()->findOrFail($id), auth()->user(), (string) ($this->rejectNotes[$id] ?? '')), __('advertising.rejected'));
        unset($this->pending);
    }

    public function pause(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->pause(Campaign::query()->findOrFail($id), auth()->user()), __('advertising.paused'));
        unset($this->pending);
    }

    public function reconcile(ReconciliationService $service): void
    {
        $report = $service->run();
        unset($this->reconciliation);
        $this->toast($report['ok'] ? __('advertising.reconciled_ok') : __('advertising.reconciled_issues'), $report['ok'] ? 'success' : 'error');
    }

    private function attempt(callable $action, string $done): void
    {
        try {
            $action();
            $this->toast($done);
        } catch (\DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }
};
?>

@php
    $stats = $this->stats;
    $currency = app(\App\Services\PlatformSettings::class)->get('advertising.currency');
    $money = fn ($minor) => number_format(((int) $minor) / 100, 2).' '.$currency;
@endphp
<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('advertising.admin_title') }}</h2>
            <p class="text-muted ms-note">{{ __('advertising.admin_description') }}</p>
        </div>
    </div>

    <div class="stat-cards ms-mb-24">
        @foreach ([
            [__('advertising.revenue_paid'), $money($stats['revenue_paid_minor'])],
            [__('advertising.revenue_earned'), $money($stats['revenue_earned_minor'])],
            [__('advertising.impressions'), number_format($stats['impressions'])],
            [__('advertising.sponsored_deliveries'), number_format($stats['sponsored_deliveries'])],
        ] as [$label, $value])
            <div class="card"><div class="card-kicker">{{ $label }}</div><div class="stat-value">{{ $value }}</div></div>
        @endforeach
    </div>

    <div class="ms-row-10 ms-wrap ms-mb-24" role="tablist">
        @foreach (['review', 'advertisers', 'packages', 'credits', 'reconciliation'] as $t)
            <button type="button" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $t }}')" class="btn {{ $tab === $t ? 'btn-primary' : '' }}">{{ __('advertising.tabs.'.$t) }}</button>
        @endforeach
    </div>

    @if ($tab === 'review')
    <p role="note" class="ms-fs-13 ms-mb-16">{{ __('advertising.policy_notice') }} <a href="{{ route('terms') }}#advertising">{{ __('advertising.policy_link') }}</a></p>
        <div class="card ms-p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('advertising.campaign') }}</th><th>{{ __('advertising.advertiser') }}</th><th>{{ __('questions.fields.status') }}</th><th>{{ __('advertising.creatives') }}</th><th class="ms-text-right">{{ __('categories.actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->pending as $campaign)
                            <tr wire:key="rc-{{ $campaign->id }}">
                                <td class="ms-fw-500">{{ $campaign->name }}</td>
                                <td>{{ $campaign->advertiser->name }}</td>
                                <td><span class="tag {{ $campaign->status === CampaignStatus::PendingReview ? 'tag-accent' : 'tag-neutral' }}">{{ __('advertising.status.'.$campaign->status->value) }}</span></td>
                                <td class="ms-fs-13">
                                    @foreach ($campaign->creatives as $creative)
                                        <div>
                                            @if ($creative->image_path)<img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($creative->image_path) }}" alt="{{ $creative->alt_text }}" style="height: 28px; display: inline-block; vertical-align: middle;">@endif
                                            <b>{{ $creative->headline }}</b> @if ($creative->link_url)<span class="text-muted">{{ $creative->link_url }}</span>@endif
                                        </div>
                                    @endforeach
                                </td>
                                <td class="ms-text-right"><div class="ms-actions-end ms-wrap">
                                    @if ($campaign->status === CampaignStatus::PendingReview)
                                        <button type="button" wire:click="approve({{ $campaign->id }})" class="btn btn-primary ms-btn-sm">{{ __('advertising.approve') }}</button>
                                    @endif
                                    @if ($campaign->status === CampaignStatus::Active)
                                        <button type="button" wire:click="pause({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.pause') }}</button>
                                    @endif
                                    @if ($campaign->status !== CampaignStatus::Exhausted)
                                        <input type="text" wire:model="rejectNotes.{{ $campaign->id }}" class="input ms-btn-sm" placeholder="{{ __('advertising.reject_reason') }}" aria-label="{{ __('advertising.reject_reason') }}">
                                        <button type="button" wire:click="reject({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.reject') }}</button>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted ms-empty">{{ __('advertising.no_campaigns') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    @if ($tab === 'advertisers')
        <div class="card ms-p-0 ms-mb-24">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('advertising.advertiser') }}</th><th>{{ __('advertising.account') }}</th><th>{{ __('advertising.credit_types.impression') }}</th><th>{{ __('advertising.credit_types.sponsored_question') }}</th><th>{{ __('advertising.campaigns') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->advertisers as $advertiser)
                            <tr wire:key="a-{{ $advertiser->id }}">
                                <td><div class="ms-fw-500">{{ $advertiser->name }}</div><div class="text-muted ms-fs-12">{{ __('advertising.types.'.$advertiser->type) }}</div></td>
                                <td class="text-muted">{{ $advertiser->user?->email }}</td>
                                <td class="tabular-nums">{{ number_format((int) $advertiser->wallets->firstWhere('credit_type', CreditType::Impression)?->balance) }}</td>
                                <td class="tabular-nums">{{ number_format((int) $advertiser->wallets->firstWhere('credit_type', CreditType::SponsoredQuestion)?->balance) }}</td>
                                <td>{{ $advertiser->campaigns_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted ms-empty">{{ __('advertising.no_advertisers') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form wire:submit="createAdvertiser" class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ __('advertising.new_advertiser') }}</h3>
            <p class="text-muted ms-fs-13">{{ __('advertising.new_advertiser_hint') }}</p>
            <x-text-input wire:model="advEmail" type="email" :placeholder="__('advertising.account_email')" :error="$errors->first('advEmail')" />
            <x-input-error :message="$errors->first('advEmail')" />
            <x-text-input wire:model="advName" type="text" :placeholder="__('advertising.advertiser_name')" :error="$errors->first('advName')" />
            <x-input-error :message="$errors->first('advName')" />
            <select wire:model="advType" class="input">@foreach (['ngo', 'school', 'university', 'association', 'company', 'other'] as $type)<option value="{{ $type }}">{{ __('advertising.types.'.$type) }}</option>@endforeach</select>
            <x-text-input wire:model="advWebsite" type="url" placeholder="https://" :error="$errors->first('advWebsite')" />
            <x-input-error :message="$errors->first('advWebsite')" />
            <div><button type="submit" class="btn btn-primary">{{ __('advertising.create_advertiser') }}</button></div>
        </form>
    @endif

    @if ($tab === 'packages')
        <div class="card ms-p-0 ms-mb-24">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('advertising.package') }}</th><th>{{ __('advertising.credit_types.impression') }}</th><th>{{ __('advertising.credit_types.sponsored_question') }}</th><th>{{ __('advertising.price') }}</th><th class="ms-text-right">{{ __('categories.actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->packages as $package)
                            <tr wire:key="p-{{ $package->id }}">
                                <td><span class="ms-fw-500">{{ $package->name }}</span> @unless ($package->is_active)<span class="tag tag-neutral">{{ __('advertising.inactive') }}</span>@endunless</td>
                                <td class="tabular-nums">{{ number_format($package->impression_credits) }}</td>
                                <td class="tabular-nums">{{ number_format($package->sponsored_question_credits) }}</td>
                                <td class="tabular-nums">{{ number_format($package->price_minor / 100, 2) }} {{ $package->currency }}</td>
                                <td class="ms-text-right"><button type="button" wire:click="editPackage({{ $package->id }})" class="btn ms-btn-sm">{{ __('categories.edit') }}</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted ms-empty">{{ __('advertising.no_packages') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <form wire:submit="savePackage" class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ $packageId ? __('advertising.edit_package') : __('advertising.new_package') }}</h3>
            <x-text-input wire:model="pkgName" type="text" :placeholder="__('advertising.package_name')" :error="$errors->first('pkgName')" />
            <x-input-error :message="$errors->first('pkgName')" />
            <label class="ms-fs-13">{{ __('advertising.credit_types.impression') }} <input type="number" min="0" wire:model="pkgImpressions" class="input"></label>
            <x-input-error :message="$errors->first('pkgImpressions')" />
            <label class="ms-fs-13">{{ __('advertising.credit_types.sponsored_question') }} <input type="number" min="0" wire:model="pkgSponsored" class="input"></label>
            <label class="ms-fs-13">{{ __('advertising.price') }} ({{ $currency }}) <input type="number" min="0" step="0.01" wire:model="pkgPrice" class="input"></label>
            <x-input-error :message="$errors->first('pkgPrice')" />
            <label class="ms-check-row"><input type="checkbox" class="checkbox" wire:model="pkgActive"> {{ __('advertising.active') }}</label>
            <div><button type="submit" class="btn btn-primary">{{ __('categories.save_changes') }}</button></div>
        </form>
    @endif

    @if ($tab === 'credits')
        <div class="ms-row-10 ms-wrap">
            <form wire:submit="recordPurchase" class="card ms-stack-16 ms-flex-1" style="min-width: 280px;">
                <h3 class="ms-fs-18">{{ __('advertising.record_purchase') }}</h3>
                <p class="text-muted ms-fs-13">{{ __('advertising.record_purchase_hint') }}</p>
                <select wire:model="walletAdvertiser" class="input"><option value="">{{ __('advertising.choose_advertiser') }}</option>@foreach ($this->advertisers as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                <x-input-error :message="$errors->first('walletAdvertiser')" />
                <select wire:model="purchasePackage" class="input"><option value="">{{ __('advertising.choose_package') }}</option>@foreach ($this->packages->where('is_active', true) as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach</select>
                <x-input-error :message="$errors->first('purchasePackage')" />
                <x-text-input wire:model="paymentReference" type="text" :placeholder="__('advertising.payment_reference')" :error="$errors->first('paymentReference')" />
                <x-input-error :message="$errors->first('paymentReference')" />
                <div><button type="submit" class="btn btn-primary">{{ __('advertising.record_purchase') }}</button></div>
            </form>

            <form wire:submit="adjustCredits" class="card ms-stack-16 ms-flex-1" style="min-width: 280px;">
                <h3 class="ms-fs-18">{{ __('advertising.adjust') }}</h3>
                <p class="text-muted ms-fs-13">{{ __('advertising.adjust_hint') }}</p>
                <select wire:model="walletAdvertiser" class="input"><option value="">{{ __('advertising.choose_advertiser') }}</option>@foreach ($this->advertisers as $a)<option value="{{ $a->id }}">{{ $a->name }}</option>@endforeach</select>
                <select wire:model="adjustType" class="input">@foreach (CreditType::cases() as $type)<option value="{{ $type->value }}">{{ __('advertising.credit_types.'.$type->value) }}</option>@endforeach</select>
                <input type="number" wire:model="adjustQuantity" class="input" aria-label="{{ __('advertising.quantity') }}">
                <x-input-error :message="$errors->first('adjustQuantity')" />
                <x-text-input wire:model="adjustNote" type="text" :placeholder="__('advertising.note')" :error="$errors->first('adjustNote')" />
                <x-input-error :message="$errors->first('adjustNote')" />
                <div><button type="submit" class="btn">{{ __('advertising.adjust') }}</button></div>
            </form>
        </div>
    @endif

    @if ($tab === 'reconciliation')
        @php($report = $this->reconciliation)
        <div class="card ms-stack-16">
            <div class="ms-row-between">
                <h3 class="ms-fs-18">{{ __('advertising.reconciliation') }}</h3>
                <button type="button" wire:click="reconcile" class="btn btn-primary">{{ __('advertising.run_reconciliation') }}</button>
            </div>
            @if (! $report)
                <p class="text-muted">{{ __('advertising.never_reconciled') }}</p>
            @else
                <p class="ms-fs-13">{{ __('advertising.last_checked', ['date' => \Illuminate\Support\Carbon::parse($report['checked_at'])->isoFormat('lll')]) }}
                    <span class="tag {{ $report['ok'] ? 'tag-accent' : 'tag-neutral' }}">{{ $report['ok'] ? __('advertising.reconciled_ok') : __('advertising.reconciled_issues') }}</span></p>
                <ul class="ms-fs-13">
                    <li>{{ __('advertising.recon.impressions_without_entry') }}: <b>{{ count($report['impressions_without_entry']) }}</b></li>
                    <li>{{ __('advertising.recon.deliveries_without_entry') }}: <b>{{ count($report['deliveries_without_entry']) }}</b></li>
                    <li>{{ __('advertising.recon.entries_without_event') }}: <b>{{ count($report['entries_without_event']) }}</b></li>
                    <li>{{ __('advertising.recon.wallet_mismatches') }}: <b>{{ count($report['wallet_mismatches']) }}</b></li>
                </ul>
                @foreach ($report['wallet_mismatches'] as $row)
                    <p class="ms-fs-12 text-muted">#{{ $row['advertiser_id'] }} · {{ __('advertising.credit_types.'.$row['credit_type']) }} · {{ $row['balance'] }} ≠ {{ $row['ledger'] }}</p>
                @endforeach
            @endif
        </div>
    @endif
</div>

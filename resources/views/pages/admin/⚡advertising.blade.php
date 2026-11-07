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

    public int $pkgRounds = 0;

    public string $pkgDescription = '';

    public int $pkgOrder = 0;

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

    /** @var array<int, string> Reasons for credit notes, per invoice. */
    public array $cancelReasons = [];

    /** @var array<int, bool> Story 16 S13: "checked against the advertising policy" per campaign. */
    public array $policyChecked = [];

    public function mount(): void
    {
        $this->authorize('manage advertising');
    }

    #[Computed]
    public function advertisers()
    {
        return Advertiser::query()->with(['user', 'wallets'])->withCount('campaigns')
            ->addSelect(['strikes_count' => \App\Domain\Advertising\Models\PolicyStrike::query()->selectRaw('count(*)')->whereColumn('advertiser_id', 'advertisers.id')])
            ->orderBy('name')->get();
    }

    #[Computed]
    public function callsDue()
    {
        return app(\App\Domain\Advertising\PolicyEnforcementService::class)->callsDue()->load('campaign.advertiser');
    }

    #[Computed]
    public function packages()
    {
        return AdvertisingPackage::query()->ordered()->get();
    }

    #[Computed]
    public function pending()
    {
        return Campaign::query()->with(['advertiser', 'creatives'])
            ->whereIn('status', [CampaignStatus::PendingReview->value, CampaignStatus::Active->value, CampaignStatus::Paused->value, CampaignStatus::Exhausted->value])
            ->orderByRaw("case when status = 'pending_review' then 0 else 1 end")->latest('id')->limit(50)->get();
    }

    #[Computed]
    public function invoices()
    {
        return \App\Domain\Advertising\Models\Invoice::query()->with(['advertiser:id,name', 'creditNote:id,cancels_invoice_id,number'])->orderByDesc('id')->limit(100)->get();
    }

    public function cancelInvoice(int $id, \App\Domain\Advertising\InvoiceService $invoices): void
    {
        $this->attempt(fn () => $invoices->cancel(\App\Domain\Advertising\Models\Invoice::query()->findOrFail($id), auth()->user(), (string) ($this->cancelReasons[$id] ?? '')), __('advertising.invoices.cancelled'));
        unset($this->invoices);
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
        $this->pkgRounds = $p->sponsored_round_credits;
        $this->pkgDescription = (string) $p->description;
        $this->pkgOrder = $p->sort_order;
        $this->pkgPrice = number_format($p->price_minor / 100, 2, '.', '');
        $this->pkgActive = $p->is_active;
    }

    public function savePackage(PlatformSettings $settings): void
    {
        $data = $this->validate([
            'pkgName' => ['required', 'string', 'max:120'],
            'pkgImpressions' => ['required', 'integer', 'min:0', 'max:100000000'],
            'pkgSponsored' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pkgRounds' => ['required', 'integer', 'min:0', 'max:1000000'],
            'pkgDescription' => ['nullable', 'string', 'max:500'],
            'pkgOrder' => ['required', 'integer', 'min:0', 'max:1000'],
            'pkgPrice' => ['required', 'numeric', 'min:0', 'max:10000000'],
            'pkgActive' => ['boolean'],
        ]);

        if ($data['pkgImpressions'] + $data['pkgSponsored'] + $data['pkgRounds'] === 0) {
            $this->addError('pkgImpressions', __('advertising.errors.empty_package'));

            return;
        }

        AdvertisingPackage::query()->updateOrCreate(['id' => $this->packageId], [
            'name' => $data['pkgName'],
            'impression_credits' => $data['pkgImpressions'],
            'sponsored_question_credits' => $data['pkgSponsored'],
            'sponsored_round_credits' => $data['pkgRounds'],
            'description' => $data['pkgDescription'] ?: null,
            'sort_order' => $data['pkgOrder'],
            'price_minor' => (int) round(((float) $data['pkgPrice']) * 100),
            'currency' => (string) $settings->get('advertising.currency'),
            'is_active' => $data['pkgActive'],
        ]);

        $this->reset(['packageId', 'pkgName', 'pkgImpressions', 'pkgSponsored', 'pkgRounds', 'pkgDescription', 'pkgOrder', 'pkgPrice', 'pkgActive']);
        unset($this->packages);
        $this->toast(__('advertising.package_saved'));
    }

    /** Copies the default values (config fakkroun.advertising_packs) back onto a standard pack. */
    public function resetPackage(int $id): void
    {
        $package = AdvertisingPackage::query()->findOrFail($id);

        if ($package->resetToDefault()) {
            unset($this->packages);
            $this->toast(__('advertising.package_reset'));
        }
    }

    /** Re-creates any standard pack that was deleted from the database. */
    public function restoreDefaultPackages(): void
    {
        AdvertisingPackage::ensureDefaults();
        unset($this->packages);
        $this->toast(__('advertising.packages_restored'));
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
        $this->attempt(fn () => $campaigns->approve(Campaign::query()->findOrFail($id), auth()->user(), (bool) ($this->policyChecked[$id] ?? false)), __('advertising.approved'));
        unset($this->pending);
    }

    public function reject(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->reject(Campaign::query()->findOrFail($id), auth()->user(), (string) ($this->rejectNotes[$id] ?? '')), __('advertising.rejected'));
        unset($this->pending);
    }

    public function breach(int $id, \App\Domain\Advertising\PolicyEnforcementService $policy): void
    {
        $this->attempt(function () use ($id, $policy): void {
            $strike = $policy->recordBreach(Campaign::query()->findOrFail($id), auth()->user(), (string) ($this->rejectNotes[$id] ?? ''));
            $this->toast(__('advertising.policy.recorded.'.min($strike->level, 3)));
        }, null);
        unset($this->pending, $this->advertisers, $this->callsDue);
    }

    public function recordCall(int $strikeId, \App\Domain\Advertising\PolicyEnforcementService $policy): void
    {
        $this->attempt(fn () => $policy->recordCall(\App\Domain\Advertising\Models\PolicyStrike::query()->findOrFail($strikeId), auth()->user()), __('advertising.policy.call_recorded'));
        unset($this->callsDue);
    }

    public function reactivate(int $id, \App\Domain\Advertising\PolicyEnforcementService $policy): void
    {
        $this->attempt(fn () => $policy->reactivate(Advertiser::query()->findOrFail($id), auth()->user()), __('advertising.policy.reactivated'));
        unset($this->advertisers);
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

    private function attempt(callable $action, ?string $done): void
    {
        try {
            $action();
            if ($done !== null) {
                $this->toast($done);
            }
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
        @foreach (['review', 'advertisers', 'packages', 'credits', 'invoices', 'reconciliation'] as $t)
            <button type="button" role="tab" aria-selected="{{ $tab === $t ? 'true' : 'false' }}" wire:click="$set('tab', '{{ $t }}')" class="btn {{ $tab === $t ? 'btn-primary' : '' }}">{{ __('advertising.tabs.'.$t) }}</button>
        @endforeach
    </div>

    @if ($tab === 'review')
        @if ($this->callsDue->isNotEmpty())
            <div class="card ms-stack-16 ms-mb-16" role="status">
                <b>{{ __('advertising.policy.calls_due') }}</b>
                @foreach ($this->callsDue as $strike)
                    <div class="ms-row-between ms-fs-13" wire:key="call-{{ $strike->id }}">
                        <span>{{ $strike->campaign?->advertiser?->name ?? '—' }} · {{ $strike->campaign?->advertiser?->contact_email }} · {{ $strike->note }}</span>
                        <button type="button" wire:click="recordCall({{ $strike->id }})" class="btn ms-btn-sm">{{ __('advertising.policy.mark_called') }}</button>
                    </div>
                @endforeach
            </div>
        @endif
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
                                            @if ($creative->keywords)<div class="text-muted ms-fs-12">{{ __('advertising.keywords') }}: {{ implode(', ', $creative->keywords) }}</div>@endif
                                        </div>
                                    @endforeach
                                    @if ($campaign->ai_review)
                                        {{-- Story 16 S8: advisory notes only; the reviewer decides. --}}
                                        <div class="ms-fs-12 ms-mt-8" role="note">
                                            <b>{{ __('advertising.ai_review.title') }}</b>
                                            @if (! empty($campaign->ai_review['error']))
                                                <span class="text-muted">{{ __('advertising.ai_review.failed') }}</span>
                                            @elseif (empty($campaign->ai_review['concerns']))
                                                <span class="text-muted">{{ __('advertising.ai_review.none') }}</span>
                                            @else
                                                <ul>
                                                    @foreach ($campaign->ai_review['concerns'] as $concern)
                                                        <li>{{ __('advertising.ai_review.principles.'.$concern['principle']) }} · #{{ $concern['creative'] }}: {{ $concern['explanation'] }}</li>
                                                    @endforeach
                                                </ul>
                                            @endif
                                            <div class="text-muted">{{ __('advertising.ai_review.advisory') }}</div>
                                        </div>
                                    @endif
                                </td>
                                <td class="ms-text-right"><div class="ms-actions-end ms-wrap">
                                    @if ($campaign->status === CampaignStatus::PendingReview)
                                        <label class="ms-check-row ms-fs-12"><input type="checkbox" class="checkbox" wire:model="policyChecked.{{ $campaign->id }}"> <a href="{{ route('terms') }}#advertising" target="_blank" rel="noopener">{{ __('advertising.policy_checked') }}</a></label>
                                        <button type="button" wire:click="approve({{ $campaign->id }})" class="btn btn-primary ms-btn-sm">{{ __('advertising.approve') }}</button>
                                    @endif
                                    @if ($campaign->status === CampaignStatus::Active)
                                        <button type="button" wire:click="pause({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.pause') }}</button>
                                    @endif
                                    @if ($campaign->status !== CampaignStatus::Exhausted)
                                        <input type="text" wire:model="rejectNotes.{{ $campaign->id }}" class="input ms-btn-sm" placeholder="{{ __('advertising.reject_reason') }}" aria-label="{{ __('advertising.reject_reason') }}">
                                        <button type="button" wire:click="reject({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.reject') }}</button>
                                        <button type="button" wire:click="breach({{ $campaign->id }})" wire:confirm="{{ __('advertising.policy.confirm') }}" class="btn ms-btn-sm ms-outline-danger">{{ __('advertising.policy.breach') }}</button>
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
                    <thead><tr><th>{{ __('advertising.advertiser') }}</th><th>{{ __('advertising.account') }}</th><th>{{ __('advertising.credit_types.impression') }}</th><th>{{ __('advertising.credit_types.sponsored_question') }}</th><th>{{ __('advertising.campaigns') }}</th><th>{{ __('advertising.policy.strikes') }}</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($this->advertisers as $advertiser)
                            <tr wire:key="a-{{ $advertiser->id }}">
                                <td><div class="ms-fw-500">{{ $advertiser->name }}</div><div class="text-muted ms-fs-12">{{ __('advertising.types.'.$advertiser->type) }}</div></td>
                                <td class="text-muted">{{ $advertiser->user?->email }}</td>
                                <td class="tabular-nums">{{ number_format((int) $advertiser->wallets->firstWhere('credit_type', CreditType::Impression)?->balance) }}</td>
                                <td class="tabular-nums">{{ number_format((int) $advertiser->wallets->firstWhere('credit_type', CreditType::SponsoredQuestion)?->balance) }}</td>
                                <td>{{ $advertiser->campaigns_count }}</td>
                                <td>{{ (int) $advertiser->strikes_count }} / {{ \App\Domain\Advertising\PolicyEnforcementService::SUSPEND_AT }}@if ($advertiser->status === 'suspended') <span class="tag tag-neutral">{{ __('advertising.policy.suspended') }}</span>@endif</td>
                                <td class="ms-text-right">@if ($advertiser->status === 'suspended')<button type="button" wire:click="reactivate({{ $advertiser->id }})" class="btn ms-btn-sm">{{ __('advertising.policy.reactivate') }}</button>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="text-muted ms-empty">{{ __('advertising.no_advertisers') }}</td></tr>
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
                    <thead><tr><th>{{ __('advertising.package') }}</th><th>{{ __('advertising.credit_types.impression') }}</th><th>{{ __('advertising.credit_types.sponsored_question') }}</th><th>{{ __('advertising.credit_types.sponsored_round') }}</th><th>{{ __('advertising.price') }}</th><th class="ms-text-right">{{ __('categories.actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->packages as $package)
                            <tr wire:key="p-{{ $package->id }}">
                                <td><span class="ms-fw-500">{{ $package->name }}</span>
                                    @if ($package->slug)<span class="tag tag-accent">{{ __('advertising.standard_pack') }}</span>@endif
                                    @unless ($package->is_active)<span class="tag tag-neutral">{{ __('advertising.inactive') }}</span>@endunless
                                    @if ($package->description)<div class="text-muted ms-fs-12">{{ $package->description }}</div>@endif</td>
                                <td class="tabular-nums">{{ number_format($package->impression_credits) }}</td>
                                <td class="tabular-nums">{{ number_format($package->sponsored_question_credits) }}</td>
                                <td class="tabular-nums">{{ number_format($package->sponsored_round_credits) }}</td>
                                <td class="tabular-nums">{{ number_format($package->price_minor / 100, 2) }} {{ $package->currency }}</td>
                                <td class="ms-text-right"><div class="ms-actions-end">
                                    <button type="button" wire:click="editPackage({{ $package->id }})" class="btn ms-btn-sm">{{ __('categories.edit') }}</button>
                                    @if ($package->slug)<button type="button" wire:click="resetPackage({{ $package->id }})" wire:confirm="{{ __('advertising.reset_package_confirm') }}" class="btn ms-btn-sm">{{ __('advertising.reset_package') }}</button>@endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted ms-empty">{{ __('advertising.no_packages') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="ms-mb-16"><button type="button" wire:click="restoreDefaultPackages" class="btn ms-btn-sm">{{ __('advertising.restore_packages') }}</button>
            <span class="text-muted ms-fs-12">{{ __('advertising.restore_packages_hint') }}</span></div>

        <form wire:submit="savePackage" class="card ms-stack-16">
            <h3 class="ms-fs-18">{{ $packageId ? __('advertising.edit_package') : __('advertising.new_package') }}</h3>
            <x-text-input wire:model="pkgName" type="text" :placeholder="__('advertising.package_name')" :error="$errors->first('pkgName')" />
            <x-input-error :message="$errors->first('pkgName')" />
            <label class="ms-fs-13">{{ __('advertising.credit_types.impression') }} <input type="number" min="0" wire:model="pkgImpressions" class="input"></label>
            <x-input-error :message="$errors->first('pkgImpressions')" />
            <label class="ms-fs-13">{{ __('advertising.credit_types.sponsored_question') }} <input type="number" min="0" wire:model="pkgSponsored" class="input"></label>
            <label class="ms-fs-13">{{ __('advertising.credit_types.sponsored_round') }} <input type="number" min="0" wire:model="pkgRounds" class="input"></label>
            <x-text-input wire:model="pkgDescription" type="text" :placeholder="__('advertising.package_description')" />
            <label class="ms-fs-13">{{ __('advertising.package_order') }} <input type="number" min="0" wire:model="pkgOrder" class="input"></label>
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

    @if ($tab === 'invoices')
        <div class="card ms-p-0">
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>{{ __('advertising.invoices.number') }}</th><th>{{ __('advertising.advertiser') }}</th><th>{{ __('advertising.date') }}</th><th>{{ __('advertising.invoices.amount') }}</th><th class="ms-text-right">{{ __('categories.actions') }}</th></tr></thead>
                    <tbody>
                        @forelse ($this->invoices as $invoice)
                            <tr wire:key="ai-{{ $invoice->id }}">
                                <td><div class="ms-fw-500">{{ $invoice->number }}</div><div class="text-muted ms-fs-12">{{ $invoice->isCreditNote() ? __('advertising.invoices.credit_note') : __('advertising.invoices.invoice') }}@if ($invoice->creditNote) · {{ __('advertising.invoices.cancelled_by') }} {{ $invoice->creditNote->number }}@endif</div></td>
                                <td>{{ $invoice->advertiser?->name }}</td>
                                <td class="text-muted ms-nowrap">{{ $invoice->issued_at->isoFormat('ll') }}</td>
                                <td class="tabular-nums">{{ number_format($invoice->amount_minor / 100, 2) }} {{ $invoice->currency }}</td>
                                <td class="ms-text-right"><div class="ms-actions-end ms-wrap">
                                    <a href="{{ route('advertiser.invoices.pdf', $invoice) }}" class="btn ms-btn-sm">{{ __('advertising.invoices.download') }}</a>
                                    @if (! $invoice->isCreditNote() && ! $invoice->creditNote)
                                        <input type="text" wire:model="cancelReasons.{{ $invoice->id }}" class="input ms-btn-sm" placeholder="{{ __('advertising.invoices.cancel_reason') }}" aria-label="{{ __('advertising.invoices.cancel_reason') }}">
                                        <button type="button" wire:click="cancelInvoice({{ $invoice->id }})" wire:confirm="{{ __('advertising.invoices.cancel') }}?" class="btn ms-btn-sm">{{ __('advertising.invoices.cancel') }}</button>
                                    @endif
                                </div></td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="text-muted ms-empty">{{ __('advertising.invoices.none') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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

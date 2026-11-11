<?php

use App\Domain\Advertising\AdvertisingDocuments;
use App\Domain\Advertising\AdvertisingStats;
use App\Domain\Advertising\CampaignService;
use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Campaign;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Services\CategoryService;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Advertiser dashboard (spec §20). */
new #[Layout('layouts::app')] #[Title('Advertiser dashboard')] class extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    #[Url] public string $from = '';

    #[Url] public string $to = '';

    #[Url] public string $campaignId = '';

    #[Url] public string $categoryId = '';

    #[Url] public string $language = '';

    public string $newName = '';

    /** @var array<int, string> */
    public array $newCategories = [];

    /** @var array<int, string> */
    public array $newLanguages = [];

    public string $newStarts = '';

    public string $newEnds = '';

    public ?int $creativeFor = null;

    public string $headline = '';

    public string $body = '';

    public string $linkUrl = '';

    public string $creativeLanguage = '';

    /** Comma-separated topic words (story 16 S1). */
    public string $keywords = '';

    /** @var \Illuminate\Http\UploadedFile|null */
    public $image = null;

    public function mount(): void
    {
        abort_unless($this->advertiser() !== null, 403);
    }

    public function advertiser()
    {
        return app(CampaignService::class)->advertiserFor(auth()->user());
    }

    #[Computed]
    public function stats(): array
    {
        return app(AdvertisingStats::class)->forAdvertiser($this->advertiser(), [
            'from' => $this->from ?: null, 'to' => $this->to ?: null, 'campaign_id' => $this->campaignId ?: null,
            'category_id' => $this->categoryId ?: null, 'language' => $this->language ?: null,
        ]);
    }

    #[Computed]
    public function campaigns()
    {
        return $this->advertiser()->campaigns()->withCount('creatives')->latest('id')->get();
    }

    #[Computed]
    public function ledger()
    {
        return app(\App\Domain\Advertising\LedgerEntryService::class)->query()->where('advertiser_id', $this->advertiser()->id)->orderByDesc('id')->paginate(15, pageName: 'ledger');
    }

    #[Computed]
    public function invoices()
    {
        return app(\App\Domain\Advertising\InvoiceService::class)->query()->where('advertiser_id', $this->advertiser()->id)->orderByDesc('id')->limit(50)->get();
    }

    public string $statementMonth = '';

    public function downloadInvoice(int $invoiceId, AdvertisingDocuments $documents): StreamedResponse
    {
        $invoice = app(\App\Domain\Advertising\InvoiceService::class)->query()
            ->where('advertiser_id', $this->advertiser()->id)
            ->findOrFail($invoiceId);

        return $documents->invoice($invoice, auth()->user());
    }

    public function downloadStatement(AdvertisingDocuments $documents): StreamedResponse
    {
        return $documents->statement($this->advertiser(), $this->statementMonth ?: now()->format('Y-m'), auth()->user());
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->parentOptions();
    }

    public function createCampaign(CampaignService $campaigns): void
    {
        try {
            $campaigns->create($this->advertiser(), auth()->user(), [
                'name' => $this->newName,
                'starts_at' => $this->newStarts ?: null,
                'ends_at' => $this->newEnds ?: null,
                'target_category_ids' => array_map('intval', $this->newCategories),
                'target_languages' => $this->newLanguages,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('new_'.\Illuminate\Support\Str::before($key, '.'), $messages[0]);
            }

            return;
        }

        $this->reset(['newName', 'newCategories', 'newLanguages', 'newStarts', 'newEnds']);
        $this->toast(__('advertising.campaign_created'));
    }

    public function addCreative(CampaignService $campaigns): void
    {
        $campaign = $this->ownCampaign($this->creativeFor);

        try {
            $campaigns->addCreative($campaign, auth()->user(), [
                'headline' => $this->headline, 'body' => $this->body ?: null, 'link_url' => $this->linkUrl ?: null,
                'language' => $this->creativeLanguage ?: null, 'alt_text' => $this->headline,
                'keywords' => array_values(array_unique(array_filter(array_map('trim', preg_split('/[,،]/u', $this->keywords) ?: [])))),
            ], $this->image);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError('creative_'.$key, $messages[0]);
            }

            return;
        }

        $this->reset(['creativeFor', 'headline', 'body', 'linkUrl', 'creativeLanguage', 'image', 'keywords']);
        $this->toast(__('advertising.creative_added'));
    }

    public function submit(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->submit($this->ownCampaign($id), auth()->user()), __('advertising.submitted'));
    }

    public function pause(int $id, CampaignService $campaigns): void
    {
        $this->attempt(fn () => $campaigns->pause($this->ownCampaign($id), auth()->user()), __('advertising.paused'));
    }

    private function ownCampaign(?int $id): Campaign
    {
        return $this->advertiser()->campaigns()->whereKey($id)->firstOrFail();
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

@php($advertiser = $this->advertiser())
@php($stats = $this->stats)
@php($money = fn (int $minor) => number_format($minor / 100, 2).' '.app(\App\Services\PlatformSettings::class)->get('advertising.currency'))
<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('advertising.dashboard_title', ['name' => $advertiser->name]) }}</h2>
            <p class="text-muted ms-note">{{ __('advertising.dashboard_description') }}</p>
        </div>
    </div>

    @if ($advertiser->status === 'suspended')
        <div role="alert" class="tag tag-outline ms-notice">{{ __('advertising.policy.suspended_notice') }}</div>
    @endif
    @php($strikes = app(\App\Domain\Advertising\PolicyStrikeService::class)->query()->where('advertiser_id', $advertiser->id)->count())
    @if ($strikes > 0 && $advertiser->status !== 'suspended')
        <div role="status" class="tag tag-outline ms-notice">{{ __('advertising.policy.strikes_notice', ['count' => $strikes, 'max' => \App\Domain\Advertising\PolicyEnforcementService::SUSPEND_AT]) }}</div>
    @endif
    <p role="note" class="ms-fs-13 ms-mb-24">{{ __('advertising.policy_notice') }} <a href="{{ route('terms') }}#advertising">{{ __('advertising.policy_link') }}</a></p>

    <div class="stat-cards ms-mb-24">
        @foreach ([
            [__('advertising.credit_types.impression'), number_format($advertiser->balance(CreditType::Impression)), __('advertising.available')],
            [__('advertising.credit_types.sponsored_question'), number_format($advertiser->balance(CreditType::SponsoredQuestion)), __('advertising.available')],
            [__('advertising.credit_types.sponsored_round'), number_format($advertiser->balance(CreditType::SponsoredRound)), __('advertising.rounds_hint', ['count' => number_format($stats['sponsored_rounds'])])],
            [__('advertising.impressions'), number_format($stats['impressions']), __('advertising.offered_hint', ['count' => number_format($stats['offered'])])],
            [__('advertising.clicks'), number_format($stats['clicks']), $stats['ctr'] === null ? '—' : __('advertising.ctr', ['rate' => $stats['ctr']])],
            [__('advertising.players_reached'), number_format($stats['reach']), __('advertising.consumed', ['count' => number_format($stats['consumed_impression_credits'])])],
            [__('advertising.sponsored_deliveries'), number_format($stats['sponsored_deliveries']), __('advertising.reach', ['count' => number_format($stats['sponsored_reach'])])],
            [__('advertising.spend'), $money($stats['spend_minor']), __('advertising.spend_hint')],
        ] as [$label, $value, $hint])
            <div class="card"><div class="card-kicker">{{ $label }}</div><div class="stat-value">{{ $value }}</div><div class="text-muted ms-fs-12">{{ $hint }}</div></div>
        @endforeach
    </div>

    <div class="ms-toolbar">
        <div class="ms-row-10 ms-wrap">
            <input type="date" wire:model.live="from" class="input" aria-label="{{ __('advertising.from') }}">
            <input type="date" wire:model.live="to" class="input" aria-label="{{ __('advertising.to') }}">
            <select wire:model.live="campaignId" class="input" aria-label="{{ __('advertising.campaign') }}"><option value="">{{ __('advertising.all_campaigns') }}</option>@foreach ($this->campaigns as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>
            <select wire:model.live="categoryId" class="input" aria-label="{{ __('questions.fields.category') }}"><option value="">{{ __('questions.admin.all_categories') }}</option>@foreach ($this->categories as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select>
            <select wire:model.live="language" class="input" aria-label="{{ __('questions.fields.language') }}"><option value="">{{ __('questions.admin.all_languages') }}</option>@foreach (config('app.available_locales') as $code => $meta)<option value="{{ $code }}">{{ $meta['label'] }}</option>@endforeach</select>
        </div>
    </div>

    <div class="ms-row-10 ms-wrap ms-mb-24">
        @foreach (['by_category' => __('advertising.by_category'), 'by_language' => __('advertising.by_language'), 'by_placement' => __('advertising.by_placement'), 'daily' => __('advertising.daily')] as $key => $title)
            <div class="card ms-flex-1" style="min-width: 240px;">
                <div class="card-kicker">{{ $title }}</div>
                <ul class="ms-fs-13">
                    @forelse ($stats[$key] as $label => $count)
                        <li class="ms-row-between"><span>{{ match ($key) { 'by_category' => app(\App\Services\CategoryService::class)->find($label)?->name ?? '—', 'by_placement' => __('advertising.placements.'.$label), default => $label ?: '—' } }}</span><b>{{ number_format($count) }}</b></li>
                    @empty
                        <li class="text-muted">—</li>
                    @endforelse
                </ul>
            </div>
        @endforeach
    </div>

    @if ($stats['by_creative'] !== [])
        <div class="card ms-p-0 ms-mb-24">
            <div class="row-cards-wrap">
                <div class="row-cards">
                    <caption class="ms-fs-13 ms-fw-500" style="text-align: start; padding: 12px 16px 0;">{{ __('advertising.by_creative') }}</caption>
                        @foreach ($stats['by_creative'] as $row)
                            <div class="row-card"><div class="row-card-title">{{ $row['headline'] }}</div><div class="row-card-field tabular-nums" data-label="{{ __('advertising.impressions') }}">{{ number_format($row['impressions']) }}</div><div class="row-card-field tabular-nums" data-label="{{ __('advertising.clicks') }}">{{ number_format($row['clicks']) }}</div><div class="row-card-field tabular-nums" data-label="{{ __('advertising.ctr_short') }}">{{ $row['ctr'] === null ? '—' : $row['ctr'].' %' }}</div></div>
                        @endforeach
                </div>
            </div>
        </div>
    @endif

    <div class="card ms-p-0 ms-mb-24">
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->campaigns as $campaign)
                        <div class="row-card" wire:key="c-{{ $campaign->id }}">
                            <div class="row-card-title"><div class="ms-fw-500">{{ $campaign->name }}</div><div class="text-muted ms-fs-12">{{ trans_choice('advertising.creatives_count', $campaign->creatives_count, ['count' => $campaign->creatives_count]) }}@if ($campaign->review_note) · {{ $campaign->review_note }}@endif</div></div>
                            <div class="row-card-field" data-label="{{ __('questions.fields.status') }}"><span class="tag {{ $campaign->status->value === 'active' ? 'tag-accent' : 'tag-neutral' }}">{{ __('advertising.status.'.$campaign->status->value) }}</span></div>
                            <div class="row-card-field text-muted ms-fs-12" data-label="{{ __('advertising.dates') }}">{{ $campaign->starts_at?->isoFormat('ll') ?? '—' }} → {{ $campaign->ends_at?->isoFormat('ll') ?? '—' }}</div>
                            <div class="row-card-field" data-label="{{ __('advertising.impressions') }}">{{ number_format($stats['by_campaign'][$campaign->id] ?? 0) }}</div>
                            <div class="row-card-actions"><div class="ms-actions-end">
                                <button type="button" wire:click="$set('creativeFor', {{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.add_creative') }}</button>
                                @if (in_array($campaign->status->value, ['draft', 'rejected', 'paused'], true))<button type="button" wire:click="submit({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.submit') }}</button>@endif
                                @if ($campaign->status->value === 'active')<button type="button" wire:click="pause({{ $campaign->id }})" class="btn ms-btn-sm">{{ __('advertising.pause') }}</button>@endif
                            </div></div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('advertising.no_campaigns') }}</div>
                    @endforelse
            </div>
        </div>
    </div>

    @if ($creativeFor)
        <form wire:submit="addCreative" class="card ms-stack-16 ms-mb-24">
            <h3 class="ms-fs-18">{{ __('advertising.add_creative') }}</h3>
            <x-text-input wire:model="headline" type="text" :placeholder="__('advertising.headline')" :error="$errors->first('creative_headline')" />
            <x-input-error :message="$errors->first('creative_headline')" />
            <x-text-input wire:model="body" type="text" :placeholder="__('advertising.body')" />
            <x-text-input wire:model="linkUrl" type="url" placeholder="https://" :error="$errors->first('creative_link_url')" />
            <x-input-error :message="$errors->first('creative_link_url')" />
            <select wire:model="creativeLanguage" class="input"><option value="">{{ __('advertising.every_language') }}</option>@foreach (config('app.available_locales') as $code => $meta)<option value="{{ $code }}">{{ $meta['label'] }}</option>@endforeach</select>
            <x-text-input wire:model="keywords" type="text" :placeholder="__('advertising.keywords_placeholder')" :error="$errors->first('creative_keywords')" />
            <p class="text-muted ms-fs-12">{{ __('advertising.keywords_hint', ['max' => app(\App\Services\PlatformSettings::class)->get('advertising.max_keywords_per_creative')]) }}</p>
            <x-input-error :message="$errors->first('creative_keywords') ?: $errors->first('creative_keywords.0')" />
            <input type="file" wire:model="image" accept="image/*" class="input">
            <x-input-error :message="$errors->first('creative_image')" />
            <p class="text-muted ms-fs-12">{{ __('advertising.review_notice') }}</p>
            <div class="ms-row-10"><button type="submit" class="btn btn-primary">{{ __('categories.save_changes') }}</button><button type="button" wire:click="$set('creativeFor', null)" class="btn">{{ __('categories.cancel') }}</button></div>
        </form>
    @endif

    <form wire:submit="createCampaign" class="card ms-stack-16 ms-mb-24">
        <h3 class="ms-fs-18">{{ __('advertising.new_campaign') }}</h3>
        <x-text-input wire:model="newName" type="text" :placeholder="__('advertising.campaign_name')" :error="$errors->first('new_name')" />
        <x-input-error :message="$errors->first('new_name')" />
        <div class="ms-row-10 ms-wrap">
            <input type="date" wire:model="newStarts" class="input" aria-label="{{ __('advertising.from') }}">
            <input type="date" wire:model="newEnds" class="input" aria-label="{{ __('advertising.to') }}">
        </div>
        <x-input-error :message="$errors->first('new_ends_at')" />
        <div class="ms-row-10 ms-wrap">@foreach ($this->categories as $id => $label)<label class="ms-check-row"><input type="checkbox" class="checkbox" value="{{ $id }}" wire:model="newCategories"> {{ $label }}</label>@endforeach</div>
        <div class="ms-row-10 ms-wrap">@foreach (config('app.available_locales') as $code => $meta)<label class="ms-check-row"><input type="checkbox" class="checkbox" value="{{ $code }}" wire:model="newLanguages"> {{ $meta['label'] }}</label>@endforeach</div>
        <div><button type="submit" class="btn btn-primary">{{ __('advertising.create_campaign') }}</button></div>
    </form>

    <h3 class="ms-fs-18 ms-mb-8">{{ __('advertising.invoices.title') }}</h3>
    <div class="card ms-p-0 ms-mb-16">
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->invoices as $invoice)
                        <div class="row-card" wire:key="inv-{{ $invoice->id }}">
                            <div class="row-card-title ms-fw-500">{{ $invoice->number }}</div>
                            <div class="row-card-field text-muted ms-nowrap" data-label="{{ __('advertising.date') }}">{{ $invoice->issued_at->isoFormat('ll') }}</div>
                            <div class="row-card-field" data-label="{{ __('advertising.invoices.kind') }}">{{ $invoice->isCreditNote() ? __('advertising.invoices.credit_note') : __('advertising.invoices.invoice') }}</div>
                            <div class="row-card-field tabular-nums" data-label="{{ __('advertising.invoices.amount') }}">{{ number_format($invoice->amount_minor / 100, 2) }} {{ $invoice->currency }}</div>
                            <div class="row-card-field ms-text-right"><button type="button" wire:click="downloadInvoice({{ $invoice->id }})" class="btn ms-btn-sm">{{ __('advertising.invoices.download') }}</button></div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('advertising.invoices.none') }}</div>
                    @endforelse
            </div>
        </div>
    </div>
    <div class="ms-row-10 ms-wrap ms-mb-24">
        <input type="month" wire:model.live="statementMonth" class="input" aria-label="{{ __('advertising.invoices.month') }}" max="{{ now()->format('Y-m') }}">
        <button type="button" wire:click="downloadStatement" class="btn">{{ __('advertising.invoices.statement_download') }}</button>
    </div>

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->ledger as $entry)
                        <div class="row-card">
                            <div class="row-card-title text-muted ms-nowrap">{{ $entry->created_at->isoFormat('lll') }}</div>
                            <div class="row-card-field" data-label="{{ __('advertising.entry') }}">{{ __('advertising.entry_types.'.$entry->entry_type) }}@if ($entry->credit_type) · {{ __('advertising.credit_types.'.$entry->credit_type) }}@endif</div>
                            <div class="row-card-field tabular-nums" data-label="{{ __('advertising.quantity') }}">{{ $entry->quantity }}</div>
                            <div class="row-card-field tabular-nums" data-label="{{ __('advertising.amount') }}">{{ $money($entry->amount_minor) }}</div>
                            <div class="row-card-field text-muted ms-fs-12" data-label="{{ __('advertising.reference') }}">{{ $entry->billing_reference }}</div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('advertising.no_ledger') }}</div>
                    @endforelse
            </div>
        </div>
        <div class="ms-cell-top">{{ $this->ledger->links() }}</div>
    </div>
</div>

<?php

use App\Services\ActivityLogService;
use App\Services\SettingService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app')] #[Title('Activity Log')] class extends Component
{
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(as: 'log', history: true)]
    public string $logName = '';

    public function mount(): void
    {
        $this->authorize('view activity log');
    }

    /**
     * Download of the log with the current search and log-name filters.
     */
    public function export(string $format): StreamedResponse
    {
        $this->authorize('view activity log');

        return app(ActivityLogService::class)->export($format, trim($this->search), trim($this->logName));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedLogName(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function dateFormat(): string
    {
        return app(SettingService::class)->dateFormat();
    }

    public function logNames()
    {
        return app(ActivityLogService::class)->logNames();
    }

    public function activities()
    {
        return app(ActivityLogService::class)
            ->query($this->search, $this->logName)
            ->with(['causer', 'subject'])
            ->latest()
            ->paginate(10);
    }

    public function render()
    {
        return $this->view([
            'activities' => $this->activities(),
            'logNames' => $this->logNames(),
        ]);
    }
};
?>

<div data-accordion>
    <div class="ms-mb-24">
        <h2>{{ __('activity_log.title') }}</h2>
        <p class="text-muted ms-note">{{ __('activity_log.description') }}</p>
    </div>

    <div class="ms-row-12-mb16">
        <div class="ms-inline-frame">
            <div wire:click="$set('logName', '')" @class(['ms-logfilter', 'active' => $logName === ''])>
                {{ __('actions.all') }}
            </div>
            @foreach ($logNames as $name)
                <div wire:click="$set('logName', '{{ $name }}')" @class(['ms-logfilter ms-capitalize', 'active' => $logName === $name])>
                    {{ $name }}
                </div>
            @endforeach
        </div>

        <div class="ms-row-10">
            <button type="button" wire:click="export('csv')" class="btn ms-btn-sm">{{ __('actions.export_csv') }}</button>
            <button type="button" wire:click="export('json')" class="btn ms-btn-sm">{{ __('actions.export_json') }}</button>
        </div>

        <div class="ms-search-field-right">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="rgba(var(--ink),.5)" stroke-width="1.5" class="ms-input-icon"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4-4"></path></svg>
            <input wire:model.live.debounce.300ms="search" class="input ms-pl-32" placeholder="{{ __('activity_log.search_placeholder') }}">
        </div>
    </div>

    <div class="card ms-p-0">
        @forelse ($activities as $activity)
            <div wire:key="activity-{{ $activity->id }}" class="ms-list-row">
                <button
                    type="button"
                    data-accordion-toggle="{{ $activity->id }}"
                    class="ms-disclosure-btn"
                >
                    <div class="ms-row-start-12">
                        <span class="avatar">{{ $activity->causer ? Str::of($activity->causer->name)->substr(0, 2)->upper() : '—' }}</span>
                        <div>
                            <div class="ms-fs-14">
                                <span class="ms-fw-500">{{ $activity->causer?->name ?? 'System' }}</span>
                                <span class="text-muted">{{ $activity->description }}</span>
                                @if ($activity->subject)
                                    <span class="text-muted">— {{ class_basename($activity->subject_type) }} #{{ $activity->subject_id }}</span>
                                @endif
                            </div>
                            <div class="text-muted ms-fs-11-mt2">
                                {{ $activity->created_at->diffForHumans() }} · {{ $activity->created_at->format($this->dateFormat.' H:i') }}
                                @if ($activity->log_name)
                                    · <span class="ms-capitalize">{{ $activity->log_name }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    @if ($activity->properties->isNotEmpty())
                        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="rgba(var(--ink),.5)" stroke-width="1.75" class="ms-chevron" data-accordion-chevron="{{ $activity->id }}"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path></svg>
                    @endif
                </button>

                @if ($activity->properties->isNotEmpty())
                    <div data-accordion-panel="{{ $activity->id }}">
                        <div class="prop-grid ms-inset-note">
                            @if ($activity->properties->has('old'))
                                <div>
                                    <div class="card-kicker">{{ __('actions.before') }}</div>
                                    <pre class="ms-pre">{{ json_encode($activity->properties->get('old'), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                            @if ($activity->properties->has('attributes'))
                                <div>
                                    <div class="card-kicker">{{ __('activity_log.attributes') }}</div>
                                    <pre class="ms-pre">{{ json_encode($activity->properties->get('attributes'), JSON_PRETTY_PRINT) }}</pre>
                                </div>
                            @endif
                        </div>
                    </div>
                @endif
            </div>
        @empty
            <div class="text-muted ms-empty-40">{{ __('activity_log.no_activity') }}</div>
        @endforelse

        <div class="ms-cell-top">
            {{ $activities->links() }}
        </div>
    </div>

</div>

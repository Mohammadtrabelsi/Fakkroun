<?php

use App\Services\HealthCheckService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Application health')] class extends Component
{
    use InteractsWithToasts;

    public function mount(): void
    {
        $this->authorize('view system health');
    }

    #[Computed]
    public function results()
    {
        return app(HealthCheckService::class)->latest();
    }

    public function runChecks(HealthCheckService $health): void
    {
        $this->authorize('view system health');

        $health->refresh();
        unset($this->results);

        $this->toast(__('health.refreshed'));
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2>{{ __('health.title') }}</h2>
            <p class="text-muted ms-note">
                {{ __('health.description') }}
                @if ($this->results)
                    · {{ __('health.last_run', ['time' => \Illuminate\Support\Carbon::instance($this->results->finishedAt)->diffForHumans()]) }}
                @endif
            </p>
        </div>
        <button type="button" wire:click="runChecks" wire:loading.attr="disabled" wire:target="runChecks" class="btn btn-primary">
            <span wire:loading.remove wire:target="runChecks">{{ __('health.run_now') }}</span>
            <span wire:loading wire:target="runChecks">{{ __('health.running') }}</span>
        </button>
    </div>

    <div class="card ms-p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('health.check') }}</th>
                        <th>{{ __('health.status') }}</th>
                        <th>{{ __('health.summary') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->results?->storedCheckResults ?? [] as $result)
                        <tr wire:key="check-{{ $result->name }}">
                            <td class="ms-fw-500">{{ $result->label ?: $result->name }}</td>
                            <td>
                                <span @class([
                                    'tag',
                                    'tag-accent' => $result->status === 'ok',
                                    'tag-neutral' => in_array($result->status, ['warning', 'skipped'], true),
                                    'tag-outline' => in_array($result->status, ['failed', 'crashed'], true),
                                ])>{{ __('health.status_'.$result->status) }}</span>
                            </td>
                            <td class="text-muted">
                                {{ $result->shortSummary }}
                                @if ($result->status !== 'ok' && $result->notificationMessage)
                                    <div class="ms-fs-12">{{ $result->notificationMessage }}</div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted ms-empty">{{ __('health.no_results') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

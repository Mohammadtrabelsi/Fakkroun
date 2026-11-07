<?php

use App\Services\BackupService;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Support\Number;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Backups')] class extends Component
{
    use InteractsWithToasts;

    public function mount(): void
    {
        $this->authorize('manage backups');
    }

    #[Computed]
    public function destinations(): array
    {
        return app(BackupService::class)->destinations();
    }

    public function backupNow(bool $full = false): void
    {
        $this->authorize('manage backups');

        app(BackupService::class)->queueBackup(onlyDatabase: ! $full);

        $this->toast(__('backups.queued'));
    }

    public function humanSize(float $bytes): string
    {
        return Number::fileSize($bytes, precision: 1);
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2>{{ __('backups.title') }}</h2>
            <p class="text-muted ms-note">{{ __('backups.description') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            <button type="button" wire:click="backupNow" wire:confirm="{{ __('backups.confirm_db') }}" class="btn">{{ __('backups.run_db') }}</button>
            <button type="button" wire:click="backupNow(true)" wire:confirm="{{ __('backups.confirm_full') }}" class="btn btn-primary">{{ __('backups.run_full') }}</button>
        </div>
    </div>

    <p class="text-muted ms-fs-13 ms-mb-24">{{ __('backups.queue_note') }}</p>

    @foreach ($this->destinations as $destination)
        <section class="card ms-stack-16 ms-mb-24" wire:key="dest-{{ $destination['disk'] }}">
            <div class="ms-row-10 ms-wrap">
                <h3 class="ms-mb-0">{{ __('backups.disk', ['disk' => $destination['disk']]) }}</h3>
                <span @class(['tag', 'tag-accent' => $destination['healthy'], 'tag-outline' => ! $destination['healthy']])>
                    {{ $destination['healthy'] ? __('backups.healthy') : __('backups.unhealthy') }}
                </span>
            </div>

            @if ($destination['failure'])
                <p class="text-muted ms-fs-13 ms-mb-0">{{ $destination['failure'] }}</p>
            @endif

            @if ($destination['reachable'])
                <div class="stat-cards ms-mb-0">
                    <div>
                        <div class="card-kicker">{{ __('backups.count') }}</div>
                        <div class="stat-value">{{ $destination['count'] }}</div>
                    </div>
                    <div>
                        <div class="card-kicker">{{ __('backups.newest') }}</div>
                        <div class="stat-value ms-fs-13">{{ $destination['newest']?->diffForHumans() ?? '—' }}</div>
                    </div>
                    <div>
                        <div class="card-kicker">{{ __('backups.used') }}</div>
                        <div class="stat-value ms-fs-13">{{ $this->humanSize($destination['usedBytes']) }}</div>
                    </div>
                </div>

                <div class="row-cards-wrap">
                    <div class="row-cards">
                            @forelse ($destination['backups'] as $backup)
                                <div class="row-card">
                                    <div class="row-card-title">{{ $backup['file'] }}</div>
                                    <div class="row-card-field text-muted ms-nowrap" data-label="{{ __('backups.date') }}">{{ $backup['date']->toDayDateTimeString() }}</div>
                                    <div class="row-card-actions text-muted">{{ $this->humanSize($backup['sizeBytes']) }}</div>
                                </div>
                            @empty
                                <div class="row-card row-card-empty text-muted ms-empty">{{ __('backups.none') }}</div>
                            @endforelse
                    </div>
                </div>
            @endif
        </section>
    @endforeach
</div>

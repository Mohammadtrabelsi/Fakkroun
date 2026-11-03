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

                <div class="table-wrap">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>{{ __('backups.file') }}</th>
                                <th>{{ __('backups.date') }}</th>
                                <th class="ms-text-right">{{ __('backups.size') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($destination['backups'] as $backup)
                                <tr>
                                    <td>{{ $backup['file'] }}</td>
                                    <td class="text-muted ms-nowrap">{{ $backup['date']->toDayDateTimeString() }}</td>
                                    <td class="text-muted ms-text-right">{{ $this->humanSize($backup['sizeBytes']) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="text-muted ms-empty">{{ __('backups.none') }}</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</div>

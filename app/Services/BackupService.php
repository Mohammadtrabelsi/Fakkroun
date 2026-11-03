<?php

namespace App\Services;

use App\Jobs\RunBackupJob;
use Carbon\Carbon;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\Config\Config;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatus;
use Spatie\Backup\Tasks\Monitor\BackupDestinationStatusFactory;
use Throwable;

/**
 * Read-only backup status for the admin Backups page, plus queuing an
 * on-demand run. Backup files are only listed (name, date, size), never
 * served: restoring is done from the server (see README → Backups).
 */
class BackupService
{
    /** Newest backups listed per destination. */
    public const LIST_LIMIT = 10;

    public function __construct(private readonly Config $config) {}

    /**
     * One entry per monitored destination disk. An unreachable disk is
     * reported as such instead of breaking the page.
     *
     * @return array<int, array{
     *     disk: string, name: string, reachable: bool, healthy: bool,
     *     failure: string|null, count: int, newest: Carbon|null,
     *     usedBytes: float, backups: array<int, array{file: string, date: Carbon, sizeBytes: float}>
     * }>
     */
    public function destinations(): array
    {
        return BackupDestinationStatusFactory::createForMonitorConfig($this->config->monitoredBackups)
            ->map(fn (BackupDestinationStatus $status) => $this->describe($status))
            ->values()
            ->all();
    }

    /**
     * Queues a backup. "only-db" is the cheaper default for an ad-hoc run.
     */
    public function queueBackup(bool $onlyDatabase = true): void
    {
        RunBackupJob::dispatch($onlyDatabase);
    }

    /**
     * @return array<string, mixed>
     */
    protected function describe(BackupDestinationStatus $status): array
    {
        $destination = $status->backupDestination();
        $entry = [
            'disk' => $destination->diskName(),
            'name' => $destination->backupName(),
            'reachable' => false,
            'healthy' => false,
            'failure' => null,
            'count' => 0,
            'newest' => null,
            'usedBytes' => 0.0,
            'backups' => [],
        ];

        try {
            if (! $destination->isReachable()) {
                $entry['failure'] = __('backups.unreachable');

                return $entry;
            }

            $backups = $destination->backups();

            $entry['reachable'] = true;
            $entry['healthy'] = $status->isHealthy();
            $entry['failure'] = $status->getHealthCheckFailure()?->exception()->getMessage();
            $entry['count'] = $backups->count();
            $entry['newest'] = $destination->newestBackup()?->date();
            $entry['usedBytes'] = $destination->usedStorage();
            $entry['backups'] = $backups->take(self::LIST_LIMIT)->map(fn (Backup $backup) => [
                'file' => basename($backup->path()),
                'date' => $backup->date(),
                'sizeBytes' => $backup->sizeInBytes(),
            ])->values()->all();
        } catch (Throwable $e) {
            report($e);
            $entry['failure'] = __('backups.unreachable');
        }

        return $entry;
    }
}

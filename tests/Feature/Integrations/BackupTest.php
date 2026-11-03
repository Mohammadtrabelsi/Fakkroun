<?php

namespace Tests\Feature\Integrations;

use App\Jobs\RunBackupJob;
use App\Models\User;
use App\Services\BackupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_configuration_never_archives_source_or_env_and_uses_a_private_disk(): void
    {
        $include = config('backup.backup.source.files.include');

        $this->assertNotContains(base_path(), $include);
        foreach ($include as $path) {
            $this->assertFalse(str_starts_with(base_path('.env'), $path), "{$path} would include .env");
        }
        $this->assertSame([storage_path('app/public')], $include);

        $this->assertSame(['backups'], config('backup.backup.destination.disks'));
        $disk = config('filesystems.disks.backups');
        $this->assertSame('local', $disk['driver']);
        $this->assertArrayNotHasKey('url', $disk);
        $this->assertNotTrue($disk['serve'] ?? false);
        $this->assertFalse(str_starts_with($disk['root'], public_path()), 'Backups must not live under public/.');
        $this->assertSame(config('backup.backup.destination.disks'), config('backup.monitor_backups.0.disks'));
    }

    public function test_backups_are_scheduled_with_cleanup_and_monitoring(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('backup:run')
            ->expectsOutputToContain('backup:clean')
            ->expectsOutputToContain('backup:monitor')
            ->assertSuccessful();
    }

    public function test_backups_page_requires_the_manage_backups_permission(): void
    {
        $this->get('/admin/backups')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/backups')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/backups')->assertOk()->assertSee(__('backups.title'));
    }

    public function test_admin_can_queue_a_backup_from_the_page(): void
    {
        Queue::fake();

        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.backups')
            ->call('backupNow')
            ->call('backupNow')
            ->call('backupNow', true);

        // Repeated clicks are collapsed into one pending run per mode.
        Queue::assertPushed(RunBackupJob::class, 2);

        Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job) => $job->onlyDatabase === true);
        Queue::assertPushed(RunBackupJob::class, fn (RunBackupJob $job) => $job->onlyDatabase === false);
    }

    public function test_non_admins_cannot_trigger_a_backup_through_livewire(): void
    {
        Queue::fake();

        Livewire::actingAs(User::factory()->create())
            ->test('pages::admin.backups')
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_job_runs_the_backup_command_and_fails_loudly(): void
    {
        Artisan::shouldReceive('call')->once()->with('backup:run', ['--only-db' => true])->andReturn(0);
        (new RunBackupJob(onlyDatabase: true))->handle();

        Artisan::shouldReceive('call')->once()->with('backup:run', [])->andReturn(1);
        Artisan::shouldReceive('output')->andReturn('mysqldump not found');
        $this->expectException(RuntimeException::class);
        (new RunBackupJob(onlyDatabase: false))->handle();
    }

    public function test_status_lists_backups_without_exposing_paths(): void
    {
        Storage::fake('backups');
        $name = config('backup.backup.name');
        Storage::disk('backups')->put("{$name}/2026-01-01-01-30-00.zip", str_repeat('x', 2048));
        Storage::disk('backups')->put("{$name}/2026-01-02-01-30-00.zip", str_repeat('x', 1024));

        $destinations = app(BackupService::class)->destinations();

        $this->assertCount(1, $destinations);
        $this->assertTrue($destinations[0]['reachable']);
        $this->assertSame(2, $destinations[0]['count']);
        $this->assertSame('2026-01-02-01-30-00.zip', $destinations[0]['backups'][0]['file']);
        // Both backups are older than a day, so the destination is unhealthy.
        $this->assertFalse($destinations[0]['healthy']);

        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/backups')
            ->assertSee('2026-01-02-01-30-00.zip')
            ->assertDontSee(storage_path(), false);
    }

    public function test_backup_files_are_not_reachable_over_http(): void
    {
        Storage::disk('backups')->put('probe.zip', 'secret');

        foreach (['/storage/backups/probe.zip', '/storage/probe.zip', '/backups/probe.zip'] as $url) {
            $this->assertContains($this->get($url)->status(), [403, 404], $url);
        }

        Storage::disk('backups')->delete('probe.zip');
    }
}

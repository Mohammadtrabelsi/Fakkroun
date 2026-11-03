<?php

namespace Tests\Feature\Integrations;

use App\Models\User;
use App\Services\HealthCheckService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Health\Facades\Health;
use Tests\TestCase;

class HealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /** @return array<int, string> */
    private function registeredChecks(): array
    {
        return Health::registeredChecks()->map(fn ($check) => $check->getName())->values()->all();
    }

    public function test_core_checks_are_registered(): void
    {
        $checks = $this->registeredChecks();

        foreach (['Database', 'Cache', 'UsedDiskSpace', 'Schedule', 'BackupsBackups'] as $expected) {
            $this->assertContains($expected, $checks);
        }

        // The sync test queue has no worker, so no queue check; and not production.
        $this->assertNotContains('Queue', $checks);
        $this->assertNotContains('DebugMode', $checks);
    }

    public function test_checks_run_and_results_are_stored(): void
    {
        $results = app(HealthCheckService::class)->refresh();

        $this->assertNotNull($results);
        $byName = $results->storedCheckResults->keyBy('name');
        $this->assertSame('ok', $byName['Database']->status);
        $this->assertSame('ok', $byName['Cache']->status);
        // Nothing has backed up or run the scheduler in a test.
        $this->assertSame('failed', $byName['BackupsBackups']->status);
    }

    public function test_health_page_requires_permission_and_shows_results(): void
    {
        $this->get('/admin/health')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/health')->assertForbidden();

        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/admin/health')->assertOk()->assertSee(__('health.no_results'));

        Livewire::actingAs($admin)->test('pages::admin.health')
            ->call('runChecks')
            ->assertSee('Database')
            ->assertSee(__('health.status_ok'));
    }

    public function test_json_endpoint_is_disabled_without_a_token(): void
    {
        config(['health.secret_token' => null]);

        $this->getJson('/health.json')->assertNotFound();
        $this->actingAs(User::factory()->admin()->create())->getJson('/health.json')->assertNotFound();
    }

    public function test_json_endpoint_requires_the_correct_token(): void
    {
        config(['health.secret_token' => 'monitor-secret']);
        app(HealthCheckService::class)->refresh();

        $this->getJson('/health.json')->assertUnauthorized();
        $this->getJson('/health.json', ['Authorization' => 'Bearer wrong'])->assertUnauthorized();

        // A failing check (no backups yet) is reported with a 503.
        $this->getJson('/health.json', ['Authorization' => 'Bearer monitor-secret'])
            ->assertStatus(503)
            ->assertJsonStructure(['finishedAt', 'checkResults' => [['name', 'status']]]);

        $this->getJson('/health.json', ['X-Health-Token' => 'monitor-secret'])->assertStatus(503);
    }

    public function test_health_checks_and_heartbeats_are_scheduled(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('health:check')
            ->expectsOutputToContain('health:schedule-check-heartbeat')
            ->assertSuccessful();
    }

    public function test_manual_refresh_never_sends_notifications(): void
    {
        Notification::fake();
        config(['health.notifications.enabled' => true, 'health.notifications.mail.to' => 'ops@example.com']);

        app(HealthCheckService::class)->refresh();

        Notification::assertNothingSent();
    }
}

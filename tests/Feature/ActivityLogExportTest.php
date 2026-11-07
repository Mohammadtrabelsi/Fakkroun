<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        activity('export-test')->log('Exported entry');
    }

    public function test_admin_can_export_csv(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.activity-log')
            ->call('export', 'csv');

        $this->assertStringContainsString('text/csv', $component->effects['download']['contentType']);
        $this->assertStringStartsWith('id,created_at,log_name', $this->downloadedContent($component));
    }

    public function test_admin_can_export_json_with_the_same_filters(): void
    {
        $component = Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.activity-log')
            ->set('logName', 'export-test')
            ->call('export', 'json');

        $rows = json_decode($this->downloadedContent($component), true);

        $this->assertCount(1, $rows);
        $this->assertSame('Exported entry', $rows[0]['description']);
    }

    public function test_unknown_formats_are_not_found(): void
    {
        Livewire::actingAs(User::factory()->admin()->create())
            ->test('pages::admin.activity-log')
            ->call('export', 'xml')
            ->assertNotFound();
    }

    public function test_members_cannot_export(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/admin/activity-log')
            ->assertForbidden();
    }
}

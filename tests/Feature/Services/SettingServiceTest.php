<?php

namespace Tests\Feature\Services;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingServiceTest extends TestCase
{
    use RefreshDatabase;

    private SettingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(SettingService::class);
    }

    public function test_current_creates_a_singleton_with_defaults(): void
    {
        // A data migration may already have read the settings (which creates the row): start empty.
        Setting::query()->delete();
        $this->assertSame(0, Setting::count());

        $setting = $this->service->current();

        $this->assertSame(1, Setting::count());
        $this->assertSame(config('app.name'), $setting->getTranslation('site_name', 'en'));
        $this->assertSame('', $setting->getTranslation('site_description', 'en'));
        $this->assertSame('M j, Y', $setting->date_format);
    }

    public function test_current_reuses_the_existing_row(): void
    {
        $first = $this->service->current();
        $second = $this->service->current();

        $this->assertTrue($first->is($second));
        $this->assertSame(1, Setting::count());
    }

    public function test_update_persists_changes(): void
    {
        $setting = $this->service->current();

        $updated = $this->service->update($setting, [
            'site_name' => ['en' => 'Renamed Site'],
            'support_email' => 'support@example.com',
            'maintenance_mode' => true,
        ]);

        $updated->refresh();

        $this->assertSame('Renamed Site', $updated->getTranslation('site_name', 'en'));
        $this->assertSame('support@example.com', $updated->support_email);
        $this->assertTrue($updated->maintenance_mode);
    }
}

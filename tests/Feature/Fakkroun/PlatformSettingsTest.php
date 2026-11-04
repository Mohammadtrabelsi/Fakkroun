<?php

namespace Tests\Feature\Fakkroun;

use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use App\Services\SettingService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\TestCase;

class PlatformSettingsTest extends TestCase
{
    use RefreshDatabase;

    private PlatformSettings $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->settings = app(PlatformSettings::class);
    }

    public function test_defaults_come_from_config_with_their_types(): void
    {
        $this->assertSame(20, $this->settings->get('game.max_players'));
        $this->assertSame(50, $this->settings->get('scoring.points_hard'));
        $this->assertSame(100, $this->settings->get('scoring.speed_bonus_max'));
        $this->assertTrue($this->settings->get('scoring.duplicate_rule_default'));
        $this->assertFalse($this->settings->get('submissions.auto_publish'));
        $this->assertSame('fallback', $this->settings->get('translations.missing_policy'));
    }

    public function test_overrides_are_stored_cast_and_take_precedence(): void
    {
        $this->settings->update(['game' => ['max_players' => '40'], 'scoring' => ['duplicate_rule_default' => '0']]);

        $this->assertSame(40, $this->settings->get('game.max_players'));
        $this->assertFalse($this->settings->get('scoring.duplicate_rule_default'));
        // Untouched settings keep their defaults.
        $this->assertSame(2, $this->settings->get('game.min_players'));

        // Survives a cache flush, i.e. it is persisted.
        $this->settings->flush();
        $this->assertSame(40, app(PlatformSettings::class)->get('game.max_players'));
    }

    public function test_unknown_settings_are_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->update(['game' => ['max_dragons' => 3]]);
    }

    public function test_reading_an_unknown_setting_fails_loudly(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->settings->get('game.nope');
    }

    public function test_values_are_validated(): void
    {
        $this->expectException(ValidationException::class);

        $this->settings->update(['scoring' => ['duplicate_rule_threshold' => 1]]);
    }

    public function test_fallback_language_must_be_a_supported_locale(): void
    {
        $this->settings->update(['translations' => ['fallback_locale' => 'ar']]);
        $this->assertSame('ar', $this->settings->get('translations.fallback_locale'));

        $this->expectException(ValidationException::class);
        $this->settings->update(['translations' => ['fallback_locale' => 'de']]);
    }

    public function test_cross_field_rules_are_enforced(): void
    {
        try {
            $this->settings->update(['game' => ['min_players' => 30, 'max_players' => 10]]);
            $this->fail('Expected DomainActionException.');
        } catch (DomainActionException) {
            $this->assertSame(20, $this->settings->get('game.max_players'));
        }

        $this->expectException(DomainActionException::class);
        $this->settings->update(['game' => ['default_question_seconds' => 200]]);
    }

    public function test_reset_restores_defaults(): void
    {
        $this->settings->update(['game' => ['max_players' => 50]]);

        $this->settings->reset();

        $this->assertSame(20, $this->settings->get('game.max_players'));
    }

    public function test_admin_can_save_settings_from_the_admin_page(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('admin.game-settings'))->assertOk();

        Livewire::actingAs($admin)
            ->test('pages::admin.game-settings')
            ->set('values.scoring.points_easy', 15)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(15, $this->settings->get('scoring.points_easy'));
    }

    public function test_admin_page_shows_validation_and_consistency_errors(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.game-settings')
            ->set('values.game.max_players', -5)
            ->call('save')
            ->assertHasErrors('values.game.max_players');

        Livewire::actingAs($admin)
            ->test('pages::admin.game-settings')
            ->set('values.game.min_players', 99)
            ->call('save')
            ->assertHasErrors('consistency');
    }

    public function test_only_platform_setting_managers_can_open_the_page(): void
    {
        $moderator = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Moderator->value));

        Livewire::actingAs($moderator)
            ->test('pages::admin.game-settings')
            ->assertForbidden();
    }

    public function test_changes_are_recorded_in_the_activity_log(): void
    {
        app(SettingService::class)->current();

        $this->settings->update(['game' => ['max_players' => 33]]);

        $this->assertDatabaseHas('activity_log', ['log_name' => 'settings', 'event' => 'updated']);
    }
}

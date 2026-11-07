<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Invoice;
use App\Domain\Advertising\WalletService;
use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\AdvertisingPackageSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/** The standard advertiser packs: default values, editable records. */
class DefaultPacksTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
    }

    public function test_the_three_standard_packs_exist_with_their_default_values(): void
    {
        $packs = AdvertisingPackage::query()->whereNotNull('slug')->ordered()->get();

        $this->assertSame(['essential', 'professional', 'enterprise'], $packs->pluck('slug')->all());

        foreach ($packs as $pack) {
            $defaults = config('fakkroun.advertising_packs.'.$pack->slug);
            $this->assertSame($defaults['name'], $pack->name);
            $this->assertSame($defaults['impression_credits'], $pack->impression_credits);
            $this->assertSame($defaults['sponsored_question_credits'], $pack->sponsored_question_credits);
            $this->assertSame($defaults['sponsored_round_credits'], $pack->sponsored_round_credits);
            $this->assertSame($defaults['price_minor'], $pack->price_minor);
            $this->assertSame('TND', $pack->currency);
            $this->assertTrue($pack->is_active);
        }
    }

    public function test_seeding_again_never_overwrites_admin_edits(): void
    {
        AdvertisingPackage::query()->where('slug', 'essential')->firstOrFail()->update(['price_minor' => 12345]);
        config(['fakkroun.advertising_packs.essential.price_minor' => 99999]);

        $this->seed(AdvertisingPackageSeeder::class);

        $this->assertSame(12345, AdvertisingPackage::query()->where('slug', 'essential')->value('price_minor'));
        $this->assertSame(3, AdvertisingPackage::query()->whereNotNull('slug')->count());
    }

    public function test_reset_and_restore(): void
    {
        $pack = AdvertisingPackage::query()->where('slug', 'professional')->firstOrFail();
        $pack->update(['impression_credits' => 1, 'price_minor' => 1]);

        $this->assertTrue($pack->resetToDefault());
        $this->assertSame(config('fakkroun.advertising_packs.professional.price_minor'), $pack->fresh()->price_minor);

        $custom = AdvertisingPackage::query()->create(['name' => 'Custom', 'impression_credits' => 5, 'sponsored_question_credits' => 0, 'price_minor' => 10, 'currency' => 'TND']);
        $this->assertFalse($custom->resetToDefault(), 'Custom packs have no default values.');

        AdvertisingPackage::query()->where('slug', 'enterprise')->delete();
        AdvertisingPackage::ensureDefaults();
        $this->assertTrue(AdvertisingPackage::query()->where('slug', 'enterprise')->exists());
    }

    public function test_admins_edit_and_reset_packs_from_the_screen(): void
    {
        $pack = AdvertisingPackage::query()->where('slug', 'essential')->firstOrFail();

        Livewire::actingAs($this->admin)->test('pages::admin.advertising')
            ->set('tab', 'packages')
            ->assertSee('Essential')->assertSee('Professional')->assertSee('Enterprise')
            ->call('editPackage', $pack->id)
            ->assertSet('pkgImpressions', $pack->impression_credits)
            ->set('pkgImpressions', 15000)->set('pkgPrice', '350.00')->set('pkgDescription', 'Schools')->set('pkgOrder', 5)
            ->call('savePackage')->assertHasNoErrors();

        $pack->refresh();
        $this->assertSame(15000, $pack->impression_credits);
        $this->assertSame(35000, $pack->price_minor);
        $this->assertSame('Schools', $pack->description);
        $this->assertSame(5, $pack->sort_order);
        $this->assertSame('essential', $pack->slug, 'Editing keeps it a standard pack.');

        Livewire::actingAs($this->admin)->test('pages::admin.advertising')->call('resetPackage', $pack->id);
        $this->assertSame(config('fakkroun.advertising_packs.essential.impression_credits'), $pack->fresh()->impression_credits);
    }

    public function test_buying_a_standard_pack_adds_its_credits_and_invoices_it(): void
    {
        $owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $advertiser = Advertiser::query()->create(['user_id' => $owner->id, 'name' => 'Green School']);
        $pack = AdvertisingPackage::query()->where('slug', 'professional')->firstOrFail();

        app(WalletService::class)->purchase($advertiser, $pack, $this->admin, 'CASH-P1');

        $this->assertSame($pack->impression_credits, $advertiser->balance(CreditType::Impression));
        $this->assertSame($pack->sponsored_question_credits, $advertiser->balance(CreditType::SponsoredQuestion));
        $this->assertSame($pack->sponsored_round_credits, $advertiser->balance(CreditType::SponsoredRound));
        $this->assertSame($pack->price_minor, Invoice::query()->sole()->amount_minor);
    }
}

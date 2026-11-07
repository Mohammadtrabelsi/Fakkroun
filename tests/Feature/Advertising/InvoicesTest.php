<?php

namespace Tests\Feature\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\InvoiceService;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\Invoice;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Domain\Advertising\WalletService;
use App\Enums\UserRole;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\PlatformSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use LogicException;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\TestCase;

/** Story 16 S11: invoices per pack purchase, credit notes, monthly statements. */
class InvoicesTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Advertiser $advertiser;

    private AdvertisingPackage $pack;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Admin->value));
        $this->owner = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        $this->advertiser = Advertiser::query()->create(['user_id' => $this->owner->id, 'name' => 'Green School', 'contact_email' => 'school@example.org']);
        $this->pack = AdvertisingPackage::query()->create(['name' => 'Professional', 'impression_credits' => 1000, 'sponsored_question_credits' => 5, 'price_minor' => 25000, 'currency' => 'TND']);
    }

    private function buy(string $reference): Invoice
    {
        app(WalletService::class)->purchase($this->advertiser, $this->pack, $this->admin, $reference);

        return Invoice::query()->latest('id')->firstOrFail();
    }

    public function test_every_purchase_gets_a_sequential_invoice_once(): void
    {
        $first = $this->buy('CASH-1');
        $second = $this->buy('CASH-2');
        $year = now()->format('Y');

        $this->assertSame("FK-{$year}-000001", $first->number);
        $this->assertSame("FK-{$year}-000002", $second->number);
        $this->assertSame(25000, $first->amount_minor);
        $this->assertSame('Professional', $first->package_name);
        $this->assertSame(1000, $first->impression_credits);
        $this->assertSame('CASH-1', $first->payment_reference);
        $this->assertSame('school@example.org', $first->buyer['email']);

        $payment = LedgerEntry::query()->findOrFail($first->ledger_entry_id);
        $this->assertSame($first->id, app(InvoiceService::class)->issueForPurchase($payment)->id, 'Issuing again returns the same invoice.');
        $this->assertSame(2, Invoice::query()->count());
    }

    public function test_invoices_are_immutable_and_keep_the_details_they_were_issued_with(): void
    {
        app(PlatformSettings::class)->update(['invoices' => ['seller_name' => 'Fakkroun SARL', 'number_prefix' => 'FKR']]);
        $invoice = $this->buy('CASH-3');
        app(PlatformSettings::class)->update(['invoices' => ['seller_name' => 'Renamed']]);

        $this->assertStringStartsWith('FKR-', $invoice->number);
        $this->assertSame('Fakkroun SARL', $invoice->fresh()->seller['name']);

        $this->expectException(LogicException::class);
        $invoice->update(['amount_minor' => 1]);
    }

    public function test_cancelling_issues_a_credit_note_once(): void
    {
        $invoice = $this->buy('CASH-4');
        $note = app(InvoiceService::class)->cancel($invoice, $this->admin, 'Entered twice');

        $this->assertTrue($note->isCreditNote());
        $this->assertSame(-25000, $note->amount_minor);
        $this->assertSame($invoice->id, $note->cancels_invoice_id);
        $this->assertNotSame($invoice->number, $note->number);
        $this->assertSame(1000, $this->advertiser->balance(CreditType::Impression), 'Credits are adjusted separately, never by a credit note.');

        $this->expectException(DomainActionException::class);
        app(InvoiceService::class)->cancel($invoice->fresh(), $this->admin, 'Again');
    }

    public function test_the_monthly_statement_counts_confirmed_usage_from_the_ledger(): void
    {
        $this->buy('CASH-5');
        app(WalletService::class)->consume($this->advertiser, CreditType::Impression, 1, 'impression:s1');
        app(WalletService::class)->consume($this->advertiser, CreditType::Impression, 1, 'impression:s2');
        app(WalletService::class)->consume($this->advertiser, CreditType::SponsoredQuestion, 1, 'sponsored:s3');

        $statement = app(InvoiceService::class)->statement($this->advertiser, now()->format('Y-m'));
        $rows = collect($statement['rows'])->keyBy('credit_type');

        $this->assertSame(2, $rows['impression']['quantity']);
        $this->assertSame(1, $rows['sponsored_question']['quantity']);
        $this->assertSame(2 * 5 + 500, $statement['total_minor'], 'Default prices: 5 per impression, 500 per sponsored question.');
        $this->assertSame(0, app(InvoiceService::class)->statement($this->advertiser, now()->subMonth()->format('Y-m'))['total_minor']);
    }

    public function test_invoice_pdfs_are_private_to_the_advertiser_and_admins(): void
    {
        Pdf::fake();
        $invoice = $this->buy('CASH-6');

        Livewire::actingAs($this->owner)->test('pages::advertiser.dashboard')
            ->call('downloadInvoice', $invoice->id)
            ->assertFileDownloaded();
        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.invoice' && $pdf->isDownload() && $pdf->contains([$invoice->number, 'Green School']));

        Livewire::actingAs($this->admin)->test('pages::admin.advertising')
            ->call('downloadInvoice', $invoice->id)
            ->assertFileDownloaded();

        // Another advertiser only ever reaches their own invoices.
        $stranger = tap(User::factory()->create(), fn (User $u) => $u->assignRole(UserRole::Advertiser->value));
        Advertiser::query()->create(['user_id' => $stranger->id, 'name' => 'Other Co', 'contact_email' => 'other@example.org']);
        Livewire::actingAs($stranger)->test('pages::advertiser.dashboard')
            ->call('downloadInvoice', $invoice->id)
            ->assertNotFound();
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(\App\Domain\Advertising\AdvertisingDocuments::class)->invoice($invoice, $stranger);
    }

    public function test_statement_pdfs(): void
    {
        Pdf::fake();
        $this->buy('CASH-7');

        Livewire::actingAs($this->owner)->test('pages::advertiser.dashboard')
            ->call('downloadStatement')
            ->assertFileDownloaded();
        Pdf::assertRespondedWithPdf(fn (PdfBuilder $pdf) => $pdf->viewName === 'pdf.statement');

        Livewire::actingAs($this->owner)->test('pages::advertiser.dashboard')
            ->set('statementMonth', '2026-13')
            ->call('downloadStatement')
            ->assertNotFound();
    }

    public function test_admins_cancel_invoices_from_the_advertising_screen(): void
    {
        $invoice = $this->buy('CASH-8');

        Livewire::actingAs($this->admin)->test('pages::admin.advertising')
            ->set('tab', 'invoices')
            ->assertSee($invoice->number)
            ->set('cancelReasons.'.$invoice->id, 'Wrong pack')
            ->call('cancelInvoice', $invoice->id);

        $this->assertNotNull($invoice->fresh()->creditNote);
    }

    public function test_the_advertiser_dashboard_lists_invoices(): void
    {
        $invoice = $this->buy('CASH-9');

        $this->actingAs($this->owner)->get(route('advertiser.dashboard'))
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('downloadInvoice('.$invoice->id.')', false);
    }
}

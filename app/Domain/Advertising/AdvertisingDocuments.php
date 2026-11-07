<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Invoice;
use App\Models\User;
use App\Services\PdfService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoice and monthly statement PDFs for advertisers (story 16 S11).
 * Generated on the fly from immutable records, never stored. Visible to the
 * advertiser who owns them and to advertising managers.
 */
class AdvertisingDocuments
{
    public function __construct(
        private readonly PdfService $pdf,
        private readonly InvoiceService $invoices,
    ) {}

    public function invoice(Invoice $invoice, ?User $user): StreamedResponse
    {
        $invoice->loadMissing(['advertiser', 'creditNote']);
        $this->authorizeFor($user, $invoice->advertiser);

        $cancels = $invoice->cancels_invoice_id ? Invoice::query()->find($invoice->cancels_invoice_id) : null;

        return $this->pdf->download(
            $this->pdf->document('pdf.invoice', ['invoice' => $invoice, 'cancels' => $cancels], $invoice->number),
        );
    }

    public function statement(Advertiser $advertiser, string $month, ?User $user): StreamedResponse
    {
        $this->authorizeFor($user, $advertiser);
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);

        return $this->pdf->download($this->pdf->document('pdf.statement', [
            'advertiser' => $advertiser,
            'statement' => $this->invoices->statement($advertiser, $month),
            'seller' => $this->invoices->seller(),
        ], 'statement-'.$advertiser->id.'-'.$month));
    }

    private function authorizeFor(?User $user, Advertiser $advertiser): void
    {
        abort_unless($user !== null && ($advertiser->user_id === $user->id || $user->can('manage advertising')), 403);

        // The PDF engine (DomPDF) does not join Arabic letters or lay out
        // right-to-left text, so Arabic-speaking users get the French document.
        if (app()->getLocale() === 'ar') {
            app()->setLocale('fr');
        }
    }
}

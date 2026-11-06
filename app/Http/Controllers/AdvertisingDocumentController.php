<?php

namespace App\Http\Controllers;

use App\Domain\Advertising\InvoiceService;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Invoice;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Invoice and monthly statement PDFs for advertisers (story 16 S11).
 * Generated on the fly from immutable records, never stored. Visible to the
 * advertiser who owns them and to advertising managers.
 */
class AdvertisingDocumentController extends Controller
{
    public function invoice(Request $request, Invoice $invoice, PdfService $pdf): PdfBuilder
    {
        $this->authorizeFor($request, $invoice->advertiser);

        $invoice->loadMissing(['advertiser', 'creditNote']);
        $cancels = $invoice->cancels_invoice_id ? Invoice::query()->find($invoice->cancels_invoice_id) : null;

        return $pdf->document('pdf.invoice', ['invoice' => $invoice, 'cancels' => $cancels], $invoice->number)
            ->headers(['Cache-Control' => 'private, no-store'])
            ->download($pdf->filename($invoice->number));
    }

    public function statement(Request $request, Advertiser $advertiser, string $month, PdfService $pdf, InvoiceService $invoices): PdfBuilder
    {
        $this->authorizeFor($request, $advertiser);
        abort_unless(preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) === 1, 404);

        $filename = $pdf->filename('statement-'.$advertiser->id.'-'.$month);

        return $pdf->document('pdf.statement', ['advertiser' => $advertiser, 'statement' => $invoices->statement($advertiser, $month), 'seller' => $invoices->seller()], $filename)
            ->headers(['Cache-Control' => 'private, no-store'])
            ->download($filename);
    }

    private function authorizeFor(Request $request, Advertiser $advertiser): void
    {
        $user = $request->user();

        abort_unless($user !== null && ($advertiser->user_id === $user->id || $user->can('manage advertising')), 403);

        // The PDF engine (DomPDF) does not join Arabic letters or lay out
        // right-to-left text, so Arabic-speaking users get the French document.
        if (app()->getLocale() === 'ar') {
            app()->setLocale('fr');
        }
    }
}

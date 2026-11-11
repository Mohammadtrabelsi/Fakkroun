<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\Invoice;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\Concerns\ManagesModel;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Invoices for advertiser packs and monthly usage statements (story 16 S11).
 *
 * - One invoice per recorded purchase (the "payment" ledger entry); issuing
 *   twice for the same purchase returns the existing invoice.
 * - Numbers are PREFIX-YEAR-000001, sequential per year, never reused.
 * - Invoices are immutable. Cancelling issues a credit note with its own
 *   number and the opposite amount. Credits already added are not touched
 *   here: an admin adjusts them separately, so money and credits stay apart.
 * - Seller and buyer details are copied at issue time, so later changes to
 *   settings or to the advertiser never alter an issued document.
 */
class InvoiceService
{
    /** @use ManagesModel<Invoice> */
    use ManagesModel;

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly AdvertiserService $advertisers,
        private readonly AdvertisingPackageService $advertisingPackages,
        private readonly LedgerEntryService $ledgerEntries,
    ) {}

    public function issueForPurchase(LedgerEntry $payment, ?User $actor = null): Invoice
    {
        if ($payment->entry_type !== 'payment') {
            throw new DomainActionException(__('advertising.invoices.errors.not_a_payment'));
        }

        if ($existing = Invoice::query()->where('ledger_entry_id', $payment->id)->where('kind', 'invoice')->first()) {
            return $existing;
        }

        $advertiser = $this->advertisers->query()->with('user')->findOrFail($payment->advertiser_id);
        $package = $payment->package_id ? $this->advertisingPackages->query()->find($payment->package_id) : null;

        return $this->create([
            'kind' => 'invoice',
            'advertiser_id' => $advertiser->id,
            'ledger_entry_id' => $payment->id,
            'package_name' => $package?->name ?? $payment->note,
            'impression_credits' => (int) ($package?->impression_credits ?? 0),
            'sponsored_question_credits' => (int) ($package?->sponsored_question_credits ?? 0),
            'sponsored_round_credits' => (int) ($package?->sponsored_round_credits ?? 0),
            'amount_minor' => (int) $payment->amount_minor,
            'currency' => (string) $payment->currency,
            'payment_reference' => (string) str($payment->billing_reference)->after('purchase:'),
            'buyer' => $this->buyer($advertiser),
            'created_by' => $actor?->id ?? $payment->created_by,
        ]);
    }

    public function cancel(Invoice $invoice, User $admin, string $reason): Invoice
    {
        if (! $admin->can('manage advertising')) {
            throw new AuthorizationException;
        }

        if ($invoice->isCreditNote() || $invoice->creditNote()->exists()) {
            throw new DomainActionException(__('advertising.invoices.errors.already_cancelled'));
        }

        if (trim($reason) === '') {
            throw new DomainActionException(__('questions.errors.reason_required'));
        }

        return $this->create([
            'kind' => 'credit_note',
            'advertiser_id' => $invoice->advertiser_id,
            'ledger_entry_id' => $invoice->ledger_entry_id,
            'cancels_invoice_id' => $invoice->id,
            'package_name' => $invoice->package_name,
            'impression_credits' => $invoice->impression_credits,
            'sponsored_question_credits' => $invoice->sponsored_question_credits,
            'sponsored_round_credits' => $invoice->sponsored_round_credits,
            'amount_minor' => -$invoice->amount_minor,
            'currency' => $invoice->currency,
            'payment_reference' => $invoice->payment_reference,
            'note' => trim($reason),
            'buyer' => $invoice->buyer,
            'created_by' => $admin->id,
        ]);
    }

    /**
     * What an advertiser used in a month, from the ledger (confirmed events only).
     *
     * @return array{month: Carbon, rows: array<int, array{credit_type: string, quantity: int, amount_minor: int}>, refunds_minor: int, total_minor: int, currency: string}
     */
    public function statement(Advertiser $advertiser, string $month): array
    {
        $start = Carbon::createFromFormat('Y-m', $month)->startOfMonth();
        $entries = $this->ledgerEntries->query()
            ->where('advertiser_id', $advertiser->id)
            ->whereIn('entry_type', ['consumption', 'reversal'])
            ->whereBetween('created_at', [$start, $start->copy()->endOfMonth()])
            ->get(['entry_type', 'credit_type', 'quantity', 'amount_minor', 'currency']);

        $rows = collect(CreditType::cases())->map(fn (CreditType $type) => [
            'credit_type' => $type->value,
            'quantity' => -(int) $entries->where('entry_type', 'consumption')->where('credit_type', $type->value)->sum('quantity'),
            'amount_minor' => (int) $entries->where('entry_type', 'consumption')->where('credit_type', $type->value)->sum('amount_minor'),
        ])->all();

        $refunds = (int) $entries->where('entry_type', 'reversal')->sum('amount_minor');

        return [
            'month' => $start,
            'rows' => $rows,
            'refunds_minor' => $refunds,
            'total_minor' => array_sum(array_column($rows, 'amount_minor')) + $refunds,
            'currency' => (string) ($entries->first()?->currency ?? $this->settings->get('advertising.currency')),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function seller(): array
    {
        $i = $this->settings->all()['invoices'];

        return ['name' => $i['seller_name'], 'address' => $i['seller_address'], 'tax_id' => $i['seller_tax_id'], 'payment_terms' => $i['payment_terms']];
    }

    /**
     * @return array<string, string|null>
     */
    private function buyer(Advertiser $advertiser): array
    {
        return [
            'name' => $advertiser->name,
            'email' => $advertiser->contact_email ?? $advertiser->user?->email,
            'website' => $advertiser->website,
            'type' => $advertiser->type,
        ];
    }

    /**
     * Gives the next number of the year and inserts; retries once if another
     * worker took the same number at the same moment.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function create(array $attributes): Invoice
    {
        $prefix = (string) $this->settings->get('invoices.number_prefix');

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($attributes, $prefix): Invoice {
                    $year = (int) now()->format('Y');
                    $sequence = (int) Invoice::query()->where('year', $year)->lockForUpdate()->max('sequence') + 1;

                    return Invoice::query()->create($attributes + [
                        'year' => $year,
                        'sequence' => $sequence,
                        'number' => sprintf('%s-%d-%06d', $prefix, $year, $sequence),
                        'seller' => $this->seller(),
                        'issued_at' => now(),
                    ]);
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    protected function model(): string
    {
        return Invoice::class;
    }
}

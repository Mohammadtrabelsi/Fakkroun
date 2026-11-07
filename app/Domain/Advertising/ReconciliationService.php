<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Models\AdvertiserWallet;
use App\Domain\Advertising\Models\Impression;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Domain\Advertising\Models\SponsoredDelivery;
use App\Domain\Advertising\Models\SponsoredRoundDelivery;
use Illuminate\Support\Facades\Cache;

/**
 * Cross-checks display events, the ledger and wallet balances (spec §18.5).
 * Any non-empty section is a discrepancy for an administrator to look at.
 */
class ReconciliationService
{
    public const CACHE_KEY = 'advertising.reconciliation';

    /**
     * @return array{checked_at: string, impressions_without_entry: array<int, int>, deliveries_without_entry: array<int, int|string>, entries_without_event: array<int, string>, wallet_mismatches: array<int, array<string, mixed>>, ok: bool}
     */
    public function run(): array
    {
        $entryRefs = LedgerEntry::query()->where('entry_type', 'consumption')->pluck('id', 'billing_reference');

        $impressionsWithout = Impression::query()->select(['id', 'billing_reference', 'ledger_entry_id'])->get()
            ->filter(fn (Impression $i) => ($entryRefs[$i->billing_reference] ?? null) !== $i->ledger_entry_id)
            ->pluck('id')->all();

        $deliveriesWithout = SponsoredDelivery::query()->select(['id', 'billing_reference', 'ledger_entry_id'])->get()
            ->filter(fn (SponsoredDelivery $d) => ($entryRefs[$d->billing_reference] ?? null) !== $d->ledger_entry_id)
            ->pluck('id')->all();

        // Sponsored rounds (story 16 S12) are checked the same way as single sponsored questions.
        $deliveriesWithout = array_merge($deliveriesWithout, SponsoredRoundDelivery::query()->select(['id', 'billing_reference', 'ledger_entry_id'])->get()
            ->filter(fn (SponsoredRoundDelivery $d) => ($entryRefs[$d->billing_reference] ?? null) !== $d->ledger_entry_id)
            ->map(fn (SponsoredRoundDelivery $d) => 'round:'.$d->id)->values()->all());

        $eventRefs = Impression::query()->pluck('billing_reference')
            ->merge(SponsoredDelivery::query()->pluck('billing_reference'))
            ->merge(SponsoredRoundDelivery::query()->pluck('billing_reference'))
            ->flip();
        $entriesWithout = $entryRefs->keys()->reject(fn (string $ref) => $eventRefs->has($ref))->values()->all();

        $ledgerTotals = LedgerEntry::query()->whereNotNull('credit_type')
            ->selectRaw('advertiser_id, credit_type, sum(quantity) as total')
            ->groupBy('advertiser_id', 'credit_type')
            ->get()
            ->keyBy(fn ($row) => $row->advertiser_id.':'.$row->credit_type);

        $walletMismatches = AdvertiserWallet::query()->get()
            ->map(fn (AdvertiserWallet $w) => ['advertiser_id' => $w->advertiser_id, 'credit_type' => $w->credit_type->value, 'balance' => $w->balance, 'ledger' => (int) ($ledgerTotals[$w->advertiser_id.':'.$w->credit_type->value]->total ?? 0)])
            ->filter(fn (array $row) => $row['balance'] !== $row['ledger'])
            ->values()->all();

        $report = [
            'checked_at' => now()->toIso8601String(),
            'impressions_without_entry' => $impressionsWithout,
            'deliveries_without_entry' => $deliveriesWithout,
            'entries_without_event' => $entriesWithout,
            'wallet_mismatches' => $walletMismatches,
            'ok' => $impressionsWithout === [] && $deliveriesWithout === [] && $entriesWithout === [] && $walletMismatches === [],
        ];

        Cache::forever(self::CACHE_KEY, $report);

        return $report;
    }

    public function last(): ?array
    {
        return Cache::get(self::CACHE_KEY);
    }
}

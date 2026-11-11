<?php

namespace App\Domain\Advertising;

use App\Domain\Advertising\Enums\CreditType;
use App\Domain\Advertising\Models\Advertiser;
use App\Domain\Advertising\Models\AdvertiserWallet;
use App\Domain\Advertising\Models\AdvertisingPackage;
use App\Domain\Advertising\Models\LedgerEntry;
use App\Domain\Advertising\Notifications\AdvertiserNotice;
use App\Exceptions\DomainActionException;
use App\Models\User;
use App\Services\Concerns\ManagesModel;
use App\Services\PlatformSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The only code that changes advertiser balances (spec §18.4–§18.5).
 *
 * Every change is one transaction: insert an immutable ledger entry with a
 * unique billing_reference, then move the balance with a conditional
 * UPDATE (… AND balance >= n) so concurrent workers can never overdraw.
 * A repeated billing_reference returns the original entry instead of
 * billing again (refresh, reconnect, retried request, duplicate worker).
 */
class WalletService
{
    /** @use ManagesModel<AdvertiserWallet> */
    use ManagesModel;

    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly LedgerEntryService $ledgerEntries,
        private readonly AdvertiserService $advertisers,
    ) {}

    public function currency(): string
    {
        return (string) $this->settings->get('advertising.currency');
    }

    /**
     * Records a package purchase: one money entry (payment) and one credit
     * entry per credit type, so money and credits stay separate.
     */
    public function purchase(Advertiser $advertiser, AdvertisingPackage $package, User $actor, string $paymentReference): void
    {
        if (! $actor->can('manage advertising')) {
            throw new AuthorizationException;
        }

        $reference = 'purchase:'.$paymentReference;

        if ($this->ledgerEntries->query()->where('billing_reference', $reference)->exists()) {
            throw new DomainActionException(__('advertising.errors.duplicate_payment'));
        }

        $payment = DB::transaction(function () use ($advertiser, $package, $actor, $reference): LedgerEntry {
            $payment = $this->insert([
                'advertiser_id' => $advertiser->id,
                'entry_type' => 'payment',
                'amount_minor' => $package->price_minor,
                'currency' => $package->currency,
                'package_id' => $package->id,
                'billing_reference' => $reference,
                'created_by' => $actor->id,
                'note' => $package->name,
            ]);

            foreach (CreditType::cases() as $creditType) {
                $quantity = (int) $package->{$creditType->packageColumn()};
                $type = $creditType->value;

                if ($quantity > 0) {
                    $this->move($advertiser, $creditType, $quantity, 'credit', $reference.':'.$type, ['package_id' => $package->id, 'created_by' => $actor->id, 'currency' => $package->currency]);
                }
            }

            return $payment;
        });

        // Every purchase gets an invoice (story 16 S11).
        app(InvoiceService::class)->issueForPurchase($payment, $actor);

        $advertiser->user?->notify(new AdvertiserNotice('purchase', ['package' => $package->name]));
    }

    /**
     * Uses $quantity credits. Returns the ledger entry, or the existing one
     * when this billing reference was already charged.
     *
     * @param  array<string, mixed>  $context  campaign_id, format, room_id, game_question_id, question_id, player_id
     *
     * @throws InsufficientCredits
     */
    public function consume(Advertiser $advertiser, CreditType $type, int $quantity, string $billingReference, array $context = []): LedgerEntry
    {
        if ($existing = $this->ledgerEntries->query()->where('billing_reference', $billingReference)->first()) {
            return $existing;
        }

        $price = (int) $this->settings->get($type->priceSetting());

        try {
            $entry = DB::transaction(fn () => $this->move($advertiser, $type, -$quantity, 'consumption', $billingReference, $context + [
                'unit_price_minor' => $price,
                'amount_minor' => $price * $quantity,
                'currency' => $this->currency(),
            ]));
        } catch (UniqueConstraintViolationException) {
            // A concurrent worker billed the same event first: same outcome, no double charge.
            return $this->ledgerEntries->query()->where('billing_reference', $billingReference)->firstOrFail();
        }

        $this->warnIfLow($advertiser, $type, $quantity);

        return $entry;
    }

    /**
     * Admin correction (positive or negative), audited by actor and note.
     */
    public function adjust(Advertiser $advertiser, CreditType $type, int $quantity, User $actor, string $note): LedgerEntry
    {
        if (! $actor->can('manage advertising')) {
            throw new AuthorizationException;
        }

        if ($quantity === 0 || trim($note) === '') {
            throw new DomainActionException(__('advertising.errors.adjustment'));
        }

        return DB::transaction(fn () => $this->move($advertiser, $type, $quantity, 'adjustment', 'adjust:'.$advertiser->id.':'.uniqid('', true), [
            'created_by' => $actor->id,
            'note' => trim($note),
            'currency' => $this->currency(),
        ]));
    }

    /**
     * Refunds a consumption entry by posting its mirror image.
     */
    public function reverse(LedgerEntry $entry, User $actor, string $note): LedgerEntry
    {
        if (! $actor->can('manage advertising')) {
            throw new AuthorizationException;
        }

        // Read the stored row: the caller's copy may carry unsaved (refused) changes.
        $entry = $this->ledgerEntries->query()->findOrFail($entry->getKey());

        if ($entry->entry_type !== 'consumption') {
            throw new DomainActionException(__('advertising.errors.reverse_only_consumption'));
        }

        $advertiser = $this->advertisers->query()->findOrFail($entry->advertiser_id);

        return DB::transaction(fn () => $this->move($advertiser, CreditType::from($entry->credit_type), -$entry->quantity, 'reversal', 'reverse:'.$entry->billing_reference, [
            'campaign_id' => $entry->campaign_id,
            'reverses_id' => $entry->id,
            'unit_price_minor' => $entry->unit_price_minor,
            'amount_minor' => -$entry->amount_minor,
            'currency' => $entry->currency,
            'created_by' => $actor->id,
            'note' => trim($note),
        ]));
    }

    /**
     * Ledger row + balance change. Must run inside a transaction.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function move(Advertiser $advertiser, CreditType $type, int $quantity, string $entryType, string $reference, array $attributes = []): LedgerEntry
    {
        AdvertiserWallet::query()->firstOrCreate(['advertiser_id' => $advertiser->id, 'credit_type' => $type->value]);

        $entry = $this->insert($attributes + [
            'advertiser_id' => $advertiser->id,
            'entry_type' => $entryType,
            'credit_type' => $type->value,
            'quantity' => $quantity,
            'billing_reference' => $reference,
            'currency' => $this->currency(),
        ]);

        $query = AdvertiserWallet::query()->where('advertiser_id', $advertiser->id)->where('credit_type', $type->value);

        $changed = $quantity >= 0
            ? $query->increment('balance', $quantity)
            : $query->where('balance', '>=', -$quantity)->decrement('balance', -$quantity);

        if ($changed === 0) {
            throw new InsufficientCredits(__('advertising.errors.insufficient', ['type' => __('advertising.credit_types.'.$type->value)]));
        }

        return $entry;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function insert(array $attributes): LedgerEntry
    {
        return $this->ledgerEntries->query()->create($attributes + ['created_at' => now()]);
    }

    private function warnIfLow(Advertiser $advertiser, CreditType $type, int $used): void
    {
        $threshold = (int) $this->settings->get('advertising.low_credit_threshold');
        $balance = $advertiser->balance($type);

        if ($threshold > 0 && $balance < $threshold && $balance + $used >= $threshold) {
            $advertiser->user?->notify(new AdvertiserNotice('low_credits', ['type' => __('advertising.credit_types.'.$type->value), 'balance' => $balance]));
        }
    }

    protected function model(): string
    {
        return AdvertiserWallet::class;
    }
}

<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

/**
 * Immutable invoice or credit note (story 16 S11). Corrections are credit
 * notes, never edits.
 */
class Invoice extends Model
{
    public $timestamps = false;

    protected $table = 'advertising_invoices';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'seller' => 'array',
            'buyer' => 'array',
            'issued_at' => 'datetime',
            'amount_minor' => 'integer',
            'impression_credits' => 'integer',
            'sponsored_question_credits' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Invoices are immutable; issue a credit note instead.'));
        static::deleting(fn () => throw new LogicException('Invoices are immutable; issue a credit note instead.'));
    }

    /**
     * @return BelongsTo<Advertiser, $this>
     */
    public function advertiser(): BelongsTo
    {
        return $this->belongsTo(Advertiser::class);
    }

    /**
     * The credit note that cancels this invoice, if any.
     *
     * @return HasOne<Invoice, $this>
     */
    public function creditNote(): HasOne
    {
        return $this->hasOne(Invoice::class, 'cancels_invoice_id');
    }

    public function isCreditNote(): bool
    {
        return $this->kind === 'credit_note';
    }
}

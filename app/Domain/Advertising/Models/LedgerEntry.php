<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/**
 * Immutable financial record (spec §18.5). Rows are only ever inserted;
 * corrections are new "reversal" or "adjustment" entries.
 */
class LedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'advertising_ledger_entries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'unit_price_minor' => 'integer', 'amount_minor' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Ledger entries are immutable; post a reversal instead.'));
        static::deleting(fn () => throw new LogicException('Ledger entries are immutable; post a reversal instead.'));
    }
}

<?php

namespace App\Domain\Advertising\Models;

use App\Domain\Advertising\Enums\CreditType;
use Illuminate\Database\Eloquent\Model;

/**
 * One row per (advertiser, credit type): balances can never be mixed.
 * Changed only by WalletService, always together with a ledger entry.
 */
class AdvertiserWallet extends Model
{
    protected $fillable = ['advertiser_id', 'credit_type'];

    protected function casts(): array
    {
        return ['credit_type' => CreditType::class, 'balance' => 'integer'];
    }
}

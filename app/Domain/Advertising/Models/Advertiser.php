<?php

namespace App\Domain\Advertising\Models;

use App\Domain\Advertising\Enums\CreditType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Advertiser extends Model
{
    protected $fillable = ['user_id', 'name', 'type', 'contact_email', 'website', 'status'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AdvertiserWallet, $this>
     */
    public function wallets(): HasMany
    {
        return $this->hasMany(AdvertiserWallet::class);
    }

    /**
     * @return HasMany<Campaign, $this>
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(Campaign::class);
    }

    /**
     * @return HasMany<LedgerEntry, $this>
     */
    public function ledger(): HasMany
    {
        return $this->hasMany(LedgerEntry::class)->orderByDesc('id');
    }

    public function balance(CreditType $type): int
    {
        return (int) $this->wallets()->where('credit_type', $type->value)->value('balance');
    }
}

<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PolicyStrike extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'advertiser_policy_strikes';

    protected $fillable = ['advertiser_id', 'campaign_id', 'level', 'note', 'created_by', 'called_at'];

    protected function casts(): array
    {
        return ['level' => 'integer', 'called_at' => 'datetime', 'created_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }
}

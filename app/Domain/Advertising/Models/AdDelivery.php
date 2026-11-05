<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An ad offered to one player for one question. Not billable on its own. */
class AdDelivery extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['issued_at' => 'datetime', 'expires_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Creative, $this>
     */
    public function creative(): BelongsTo
    {
        return $this->belongsTo(Creative::class, 'creative_id');
    }

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }
}

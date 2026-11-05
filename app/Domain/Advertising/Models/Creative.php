<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Creative extends Model
{
    protected $table = 'advertising_creatives';

    protected $fillable = ['campaign_id', 'format', 'language', 'headline', 'body', 'image_path', 'link_url', 'alt_text'];

    protected $attributes = ['status' => 'pending', 'format' => 'banner'];

    /**
     * @return BelongsTo<Campaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class, 'campaign_id');
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}

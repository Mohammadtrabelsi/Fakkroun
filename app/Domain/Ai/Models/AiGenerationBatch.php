<?php

namespace App\Domain\Ai\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiGenerationBatch extends Model
{
    protected $fillable = ['user_id', 'kind', 'provider', 'model', 'prompt_version', 'parameters', 'status', 'requested_count'];

    protected function casts(): array
    {
        return [
            'parameters' => 'array',
            'estimated_cost' => 'float',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<AiGenerationItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(AiGenerationItem::class, 'batch_id');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, ['completed', 'failed', 'stopped'], true);
    }
}

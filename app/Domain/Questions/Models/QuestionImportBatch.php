<?php

namespace App\Domain\Questions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One uploaded question file and the outcome of processing it (spec §11).
 */
class QuestionImportBatch extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = ['user_id', 'format', 'original_name', 'path', 'column_mapping', 'options', 'status'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return [
            'column_mapping' => 'array',
            'options' => 'array',
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
     * @return HasMany<QuestionImportError, $this>
     */
    public function errors(): HasMany
    {
        return $this->hasMany(QuestionImportError::class, 'import_batch_id')->orderBy('row_number');
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }
}

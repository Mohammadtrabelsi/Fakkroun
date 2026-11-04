<?php

namespace App\Domain\Questions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only record of every lifecycle action: who did what, from which
 * status to which, and why (spec §7.3).
 */
class QuestionModerationLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['question_id', 'translation_id', 'actor_id', 'action', 'from_status', 'to_status', 'reason', 'meta'];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => false);
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Domain\Questions\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable snapshot of a question version (spec §7.3 revision history).
 */
class QuestionRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['question_id', 'version', 'snapshot', 'changed_by', 'reason'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'version' => 'integer'];
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
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

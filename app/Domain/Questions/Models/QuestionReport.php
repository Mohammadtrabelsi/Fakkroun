<?php

namespace App\Domain\Questions\Models;

use App\Domain\Questions\Enums\ReportReason;
use App\Domain\Questions\Enums\ReportStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player's report of an incorrect, outdated or inappropriate question.
 */
class QuestionReport extends Model
{
    protected $fillable = ['question_id', 'reporter_id', 'language', 'reason', 'details'];

    protected $attributes = ['status' => 'open'];

    protected function casts(): array
    {
        return [
            'reason' => ReportReason::class,
            'status' => ReportStatus::class,
            'resolved_at' => 'datetime',
        ];
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
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }
}

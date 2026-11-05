<?php

namespace App\Domain\Ai\Models;

use App\Domain\Questions\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiGenerationItem extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['batch_id', 'payload', 'outcome', 'messages', 'question_id'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'messages' => 'array'];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }
}

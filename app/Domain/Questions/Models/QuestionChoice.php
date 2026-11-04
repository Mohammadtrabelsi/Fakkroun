<?php

namespace App\Domain\Questions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stable answer identifier. Its text is per language, in
 * QuestionTranslation::$choices keyed by this id.
 *
 * @property int $id
 * @property int $question_id
 * @property int $position
 */
class QuestionChoice extends Model
{
    protected $fillable = ['question_id', 'position'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Letter shown to players: A, B, C, D.
     */
    public function letter(): string
    {
        return chr(64 + $this->position);
    }
}

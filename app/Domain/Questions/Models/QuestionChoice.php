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

    /** Arabic answer letters (design brief): أ ب ج د. */
    public const ARABIC_LETTERS = [1 => 'أ', 2 => 'ب', 3 => 'ج', 4 => 'د'];

    /**
     * Letter shown to players: A B C D, or أ ب ج د for Arabic.
     */
    public function letter(?string $language = null): string
    {
        return $language === 'ar'
            ? (self::ARABIC_LETTERS[$this->position] ?? (string) $this->position)
            : chr(64 + $this->position);
    }
}

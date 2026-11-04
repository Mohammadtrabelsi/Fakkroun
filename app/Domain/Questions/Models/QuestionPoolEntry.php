<?php

namespace App\Domain\Questions\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A playable (question, language) pair. Maintained by QuestionPool only.
 */
class QuestionPoolEntry extends Model
{
    protected $table = 'question_language_pool';

    public $timestamps = false;

    protected $fillable = ['question_id', 'language', 'category_id', 'subcategory_id', 'difficulty', 'is_sponsored', 'random_key', 'last_served_at'];

    protected function casts(): array
    {
        return [
            'is_sponsored' => 'boolean',
            'random_key' => 'float',
            'last_served_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

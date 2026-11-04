<?php

namespace App\Domain\Gameplay\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlayerAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_question_id', 'user_id', 'choice_id', 'received_at', 'response_ms', 'is_correct', 'is_duplicate_excluded', 'points'];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'is_correct' => 'boolean',
            'is_duplicate_excluded' => 'boolean',
            'points' => 'integer',
            'response_ms' => 'integer',
            'choice_id' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

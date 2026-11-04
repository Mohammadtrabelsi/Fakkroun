<?php

namespace App\Domain\Gameplay\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GameResult extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_id', 'user_id', 'score', 'rank', 'correct_count', 'incorrect_count', 'excluded_count', 'unanswered_count', 'total_response_ms'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }
}

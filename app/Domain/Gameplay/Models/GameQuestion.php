<?php

namespace App\Domain\Gameplay\Models;

use App\Domain\Questions\Models\Question;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $question_id
 * @property int $position
 * @property string $language
 * @property Carbon|null $opened_at
 * @property Carbon|null $deadline_at
 * @property Carbon|null $closed_at
 */
class GameQuestion extends Model
{
    public $timestamps = false;

    protected $fillable = ['game_id', 'question_id', 'position', 'question_version', 'language', 'opened_at', 'deadline_at', 'closed_at', 'sponsored_round_campaign_id'];

    protected function casts(): array
    {
        return ['opened_at' => 'datetime', 'deadline_at' => 'datetime', 'closed_at' => 'datetime', 'position' => 'integer'];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * Includes soft-deleted questions so past games always render.
     *
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    /**
     * @return HasMany<PlayerAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(PlayerAnswer::class);
    }

    public function isOpen(): bool
    {
        return $this->opened_at !== null && $this->closed_at === null;
    }
}

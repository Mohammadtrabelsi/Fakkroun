<?php

namespace App\Domain\Gameplay\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $room_id
 * @property string $status in_progress | completed
 * @property array<string, mixed> $settings
 * @property int $total_questions
 * @property int $current_position
 */
class Game extends Model
{
    public const IN_PROGRESS = 'in_progress';

    public const COMPLETED = 'completed';

    protected $fillable = ['room_id', 'status', 'settings', 'total_questions', 'current_position', 'started_at', 'ended_at'];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'total_questions' => 'integer',
            'current_position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<GameQuestion, $this>
     */
    public function gameQuestions(): HasMany
    {
        return $this->hasMany(GameQuestion::class)->orderBy('position');
    }

    /**
     * @return HasMany<GameResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class)->orderBy('rank');
    }

    public function isCompleted(): bool
    {
        return $this->status === self::COMPLETED;
    }
}

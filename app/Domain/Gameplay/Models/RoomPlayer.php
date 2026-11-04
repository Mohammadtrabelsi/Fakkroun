<?php

namespace App\Domain\Gameplay\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomPlayer extends Model
{
    public $timestamps = false;

    protected $fillable = ['room_id', 'user_id', 'joined_at', 'left_at', 'last_seen_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'datetime', 'left_at' => 'datetime', 'last_seen_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

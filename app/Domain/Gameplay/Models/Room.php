<?php

namespace App\Domain\Gameplay\Models;

use App\Domain\Gameplay\Enums\RoomStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property int $id
 * @property string $ulid
 * @property string $code
 * @property int $host_id
 * @property string $name
 * @property RoomStatus $status
 * @property array<string, mixed> $settings
 * @property int $max_players
 */
class Room extends Model
{
    use HasUlids;

    protected $fillable = ['host_id', 'name', 'visibility', 'settings', 'max_players', 'expires_at', 'code'];

    protected $attributes = ['status' => 'waiting', 'visibility' => 'private'];

    /**
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    protected function casts(): array
    {
        return [
            'status' => RoomStatus::class,
            'settings' => 'array',
            'expires_at' => 'datetime',
            'max_players' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function host(): BelongsTo
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    /**
     * @return HasMany<RoomPlayer, $this>
     */
    public function memberships(): HasMany
    {
        return $this->hasMany(RoomPlayer::class);
    }

    /**
     * Players currently in the room (not left).
     *
     * @return BelongsToMany<User, $this>
     */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'room_players')
            ->withPivot(['joined_at', 'left_at', 'last_seen_at'])
            ->wherePivotNull('left_at')
            ->orderByPivot('joined_at');
    }

    /**
     * @return HasOne<Game, $this>
     */
    public function game(): HasOne
    {
        return $this->hasOne(Game::class);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    public function invitationUrl(): string
    {
        return route('rooms.invitation', $this->code);
    }
}

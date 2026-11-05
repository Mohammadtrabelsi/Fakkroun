<?php

namespace App\Domain\Gameplay\Models;

use Illuminate\Database\Eloquent\Model;

class UserAchievement extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'key', 'game_id', 'achieved_at'];

    protected function casts(): array
    {
        return ['achieved_at' => 'datetime'];
    }
}

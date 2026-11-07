<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;

class AdClick extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['clicked_at' => 'datetime'];
    }
}

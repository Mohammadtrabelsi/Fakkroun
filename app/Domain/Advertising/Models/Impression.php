<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;

class Impression extends Model
{
    public $timestamps = false;

    protected $table = 'advertising_impressions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['displayed_at' => 'datetime'];
    }
}

<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;

class SponsoredDelivery extends Model
{
    public $timestamps = false;

    protected $table = 'sponsored_question_deliveries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['delivered_at' => 'datetime'];
    }
}

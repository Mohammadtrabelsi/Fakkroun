<?php

namespace App\Domain\Advertising\Models;

use Illuminate\Database\Eloquent\Model;

/** Admin-configured package (spec §19). Prices are data, never code. */
class AdvertisingPackage extends Model
{
    protected $fillable = ['name', 'description', 'impression_credits', 'sponsored_question_credits', 'price_minor', 'currency', 'is_active', 'is_custom'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'is_custom' => 'boolean', 'impression_credits' => 'integer', 'sponsored_question_credits' => 'integer', 'price_minor' => 'integer'];
    }
}

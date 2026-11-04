<?php

namespace App\Models;

use App\Models\Concerns\TracksUserActions;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

class Setting extends Model
{
    use HasTranslations, LogsActivity, TracksUserActions;

    /** PHP date() formats an admin can pick from for displayed dates. */
    public const DATE_FORMATS = ['M j, Y', 'j M Y', 'Y-m-d', 'd/m/Y', 'm/d/Y', 'd.m.Y'];

    protected $fillable = [
        'site_name',
        'site_description',
        'support_email',
        'maintenance_mode',
        'date_format',
        'platform',
    ];

    public array $translatable = [
        'site_name',
        'site_description',
    ];

    protected $attributes = [
        'maintenance_mode' => false,
        'date_format' => 'M j, Y',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'platform' => 'array',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['site_name', 'site_description', 'support_email', 'maintenance_mode', 'date_format', 'platform'])
            ->logOnlyDirty()
            ->useLogName('settings');
    }
}

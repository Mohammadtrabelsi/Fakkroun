<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Prunes activity-log rows older than config('activitylog.clean_after_days').
Schedule::command('activitylog:clean')->daily();

// Rebuilds public/sitemap.xml (see config/sitemap.php). A failed run keeps the
// previous sitemap in place.
Schedule::command('sitemap:generate')
    ->cron(config('sitemap.schedule'))
    ->withoutOverlapping();

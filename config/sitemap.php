<?php

/*
|--------------------------------------------------------------------------
| XML Sitemap
|--------------------------------------------------------------------------
|
| Settings for `php artisan sitemap:generate` (App\Services\SitemapService).
| The sitemap is built from explicit route names and published database
| records — the site is never crawled. Every URL is generated from APP_URL,
| so set APP_URL to the canonical production domain in production.
|
| spatie/laravel-sitemap merges its own (crawler-only) defaults into this
| file under the same "sitemap" key; they are unused here.
|
*/

return [

    /*
    | Absolute path the sitemap is written to. It must live in the web root so
    | the web server serves it statically at /sitemap.xml.
    */
    'path' => public_path('sitemap.xml'),

    /*
    | Named routes of static, public, indexable pages. Auth, account, admin and
    | API routes are deliberately absent: they are private or noindex by nature.
    */
    'static_routes' => [
        'home',
        'about',
        'docs',
        'blog',
    ],

    /*
    | The sitemap protocol allows at most 50,000 URLs per file. Above this many
    | URLs the sitemap is split into sitemap-1.xml, sitemap-2.xml, … and
    | sitemap.xml becomes a sitemap index pointing at them.
    */
    'max_urls_per_file' => (int) env('SITEMAP_MAX_URLS_PER_FILE', 50000),

    /*
    | How often the scheduler regenerates the sitemap (cron expression).
    */
    'schedule' => env('SITEMAP_SCHEDULE', '0 * * * *'),

    /*
    | Queue a (de-duplicated) regeneration whenever a published post is
    | created, edited, unpublished, deleted or restored, so the sitemap is
    | fresh before the next scheduled run.
    */
    'regenerate_on_change' => (bool) env('SITEMAP_REGENERATE_ON_CHANGE', true),

];

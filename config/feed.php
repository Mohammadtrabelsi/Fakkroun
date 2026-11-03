<?php

/*
| RSS feeds (spatie/laravel-feed). Routes are registered by Route::feeds() in
| routes/web.php; each feed below becomes "feeds.{name}" at its "url".
|
| Items come from PostService::feedItems(): published, non-trashed posts only
| (the same set /blog/{slug} serves), newest first, capped at FEED_LIMIT.
*/

return [

    'enabled' => (bool) env('FEED_ENABLED', true),

    'limit' => (int) env('FEED_LIMIT', 20),

    'feeds' => [
        'blog' => [
            'items' => [App\Services\PostService::class, 'feedItems'],

            'url' => '/feed',

            'title' => env('FEED_TITLE', env('APP_NAME', 'Laravel').' — Blog'),
            'description' => env('FEED_DESCRIPTION', 'The latest posts from the blog.'),
            'language' => env('FEED_LANGUAGE', 'en-US'),

            /* Absolute URL of a feed image, or empty for none. */
            'image' => env('FEED_IMAGE', ''),

            /* rss | atom | json */
            'format' => 'rss',
            'view' => 'feed::rss',

            /* Leave empty to use the default content type of the format. */
            'type' => '',
            'contentType' => '',
        ],
    ],

];

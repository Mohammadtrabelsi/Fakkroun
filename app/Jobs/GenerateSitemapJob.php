<?php

namespace App\Jobs;

use App\Services\SitemapService;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Regenerates the sitemap in the background after published content changes.
 * Unique until it starts processing, so a burst of edits (e.g. a bulk publish)
 * queues a single run, while a change made during a run still queues another.
 */
class GenerateSitemapJob implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public int $uniqueFor = 600;

    public function handle(SitemapService $sitemap): void
    {
        $sitemap->generate();
    }
}

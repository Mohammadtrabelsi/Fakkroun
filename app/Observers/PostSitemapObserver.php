<?php

namespace App\Observers;

use App\Jobs\GenerateSitemapJob;
use App\Models\Post;

/**
 * Queues a sitemap regeneration when a change affects the public post set:
 * a published post is created/edited/deleted/restored, or a post is
 * published or unpublished. Drafts being edited queue nothing.
 */
class PostSitemapObserver
{
    public function saved(Post $post): void
    {
        if ($post->isPublished() || $post->wasChanged('status')) {
            $this->regenerate();
        }
    }

    public function deleted(Post $post): void
    {
        $this->whenPublished($post);
    }

    public function restored(Post $post): void
    {
        $this->whenPublished($post);
    }

    public function forceDeleted(Post $post): void
    {
        $this->whenPublished($post);
    }

    private function whenPublished(Post $post): void
    {
        if ($post->isPublished()) {
            $this->regenerate();
        }
    }

    private function regenerate(): void
    {
        if (config('sitemap.regenerate_on_change')) {
            GenerateSitemapJob::dispatch()->afterCommit();
        }
    }
}

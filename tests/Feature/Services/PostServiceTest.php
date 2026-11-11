<?php

namespace Tests\Feature\Services;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostServiceTest extends TestCase
{
    use RefreshDatabase;

    private PostService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PostService::class);
    }

    private function baseData(array $overrides = []): array
    {
        return array_merge([
            'title' => ['en' => 'Sample Title'],
            'body' => ['en' => 'Sample body.'],
            'category_id' => Category::factory()->create()->id,
            'user_id' => User::factory()->create()->id,
        ], $overrides);
    }

    public function test_create_derives_slug_from_title(): void
    {
        $post = $this->service->create($this->baseData(['title' => ['en' => 'Hello There']]));

        $this->assertSame('hello-there', $post->slug);
    }

    public function test_slug_falls_back_to_default_when_title_not_sluggable(): void
    {
        $post = $this->service->create($this->baseData(['title' => ['en' => '!!!']]));

        $this->assertSame('post', $post->slug);
    }

    public function test_slug_is_made_unique_with_incrementing_suffix(): void
    {
        $this->service->create($this->baseData(['title' => ['en' => 'Repeat']]));
        $second = $this->service->create($this->baseData(['title' => ['en' => 'Repeat']]));

        $this->assertSame('repeat-2', $second->slug);
    }

    public function test_publishing_on_create_sets_published_at(): void
    {
        $post = $this->service->create($this->baseData(['status' => Post::STATUS_PUBLISHED]));

        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->published_at);
    }

    public function test_draft_on_create_has_no_published_at(): void
    {
        $post = $this->service->create($this->baseData(['status' => Post::STATUS_DRAFT]));

        $this->assertNull($post->published_at);
    }

    public function test_update_to_published_sets_published_at(): void
    {
        $post = Post::factory()->create(['status' => Post::STATUS_DRAFT, 'published_at' => null]);

        $updated = $this->service->update($post, ['status' => Post::STATUS_PUBLISHED]);

        $this->assertNotNull($updated->published_at);
    }

    public function test_update_to_draft_clears_published_at(): void
    {
        $post = Post::factory()->published()->create();
        $this->assertNotNull($post->published_at);

        $updated = $this->service->update($post, ['status' => Post::STATUS_DRAFT]);

        $this->assertNull($updated->published_at);
    }

    public function test_update_keeps_the_original_published_at_when_still_published(): void
    {
        $original = now()->subDays(5)->startOfSecond();
        $post = Post::factory()->create([
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => $original,
        ]);

        $updated = $this->service->update($post, [
            'title' => ['en' => 'Edited Title'],
            'status' => Post::STATUS_PUBLISHED,
        ]);

        $this->assertTrue($original->equalTo($updated->published_at));
    }

    public function test_update_without_status_key_leaves_published_at_untouched(): void
    {
        $post = Post::factory()->published()->create();
        $before = $post->published_at;

        $updated = $this->service->update($post, ['title' => ['en' => 'Only Title Changed']]);

        $this->assertTrue($before->equalTo($updated->published_at));
    }

    public function test_delete_removes_the_post(): void
    {
        $post = Post::factory()->create();

        $this->service->delete($post);

        $this->assertSoftDeleted($post);
    }

    public function test_search_paginated_returns_all_for_null_search(): void
    {
        Post::factory()->count(3)->create();

        $this->assertSame(3, $this->service->searchPaginated(null)->total());
    }

    public function test_search_paginated_matches_translated_title(): void
    {
        // Not a Latin word: Faker's lorem text (e.g. "distinctio") must not match by chance.
        Post::factory()->create(['title' => ['en' => 'Zebrafish Headline', 'ar' => 'عنوان'], 'slug' => 's-1']);
        Post::factory()->create(['title' => ['en' => 'Unrelated', 'ar' => 'اخر'], 'slug' => 's-2']);

        $this->assertSame(1, $this->service->searchPaginated('Zebrafish')->total());
    }

    public function test_stats_returns_zeroes_when_empty(): void
    {
        $this->assertSame(
            ['total' => 0, 'published' => 0, 'drafts' => 0, 'trashed' => 0],
            $this->service->stats()
        );
    }

    public function test_stats_counts_published_and_drafts(): void
    {
        Post::factory()->published()->count(2)->create();
        Post::factory()->create(['status' => Post::STATUS_DRAFT]);

        $stats = $this->service->stats();

        $this->assertSame(3, $stats['total']);
        $this->assertSame(2, $stats['published']);
        $this->assertSame(1, $stats['drafts']);
    }
}

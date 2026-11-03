<?php

namespace Tests\Feature\Integrations;

use App\Models\Post;
use App\Models\User;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_feed_lists_only_published_untrashed_posts(): void
    {
        $author = User::factory()->create(['name' => 'Feed Author']);
        $published = Post::factory()->create([
            'title' => ['en' => 'Visible in feed'],
            'excerpt' => ['en' => 'Feed summary'],
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subHour(),
            'user_id' => $author->id,
        ]);
        app(TagService::class)->syncTo($published, ['News']);

        Post::factory()->create(['title' => ['en' => 'Secret draft'], 'status' => Post::STATUS_DRAFT]);
        Post::factory()->create(['title' => ['en' => 'Trashed post'], 'status' => Post::STATUS_PUBLISHED, 'published_at' => now()])->delete();

        $response = $this->get('/feed')->assertOk();

        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));

        $xml = simplexml_load_string($response->getContent());
        $this->assertNotFalse($xml, 'Feed is not valid XML.');

        $titles = array_map('strval', $xml->xpath('//item/title'));
        $this->assertSame(['Visible in feed'], $titles);
        $this->assertSame($published->publicUrl(), (string) $xml->xpath('//item/link')[0]);
        $this->assertStringContainsString('Feed summary', $response->getContent());
        $this->assertStringContainsString('Feed Author', $response->getContent());
        $this->assertStringContainsString('News', $response->getContent());
    }

    public function test_feed_is_newest_first_and_limited(): void
    {
        config(['feed.limit' => 2]);

        foreach (['Oldest' => 3, 'Middle' => 2, 'Newest' => 1] as $title => $daysAgo) {
            Post::factory()->create(['title' => ['en' => $title], 'status' => Post::STATUS_PUBLISHED, 'published_at' => now()->subDays($daysAgo)]);
        }

        $xml = simplexml_load_string($this->get('/feed')->getContent());

        $this->assertSame(['Newest', 'Middle'], array_map('strval', $xml->xpath('//item/title')));
    }

    public function test_public_pages_advertise_the_feed(): void
    {
        $this->get('/blog')
            ->assertSee('<link rel="alternate" type="application/rss+xml" href="'.route('feeds.blog').'"', false)
            ->assertSee(__('blog.rss'));
    }
}

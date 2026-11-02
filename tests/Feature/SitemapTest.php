<?php

namespace Tests\Feature;

use App\Jobs\GenerateSitemapJob;
use App\Models\Post;
use App\Services\PostService;
use DOMDocument;
use DOMXPath;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://www.majida-shop.com';

    private string $publicDir;

    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Write into an isolated "public" directory so tests never touch the real one.
        $this->originalPublicPath = public_path();
        $this->publicDir = storage_path('framework/testing/sitemap-'.uniqid());
        File::ensureDirectoryExists($this->publicDir);
        $this->app->usePublicPath($this->publicDir);

        config([
            'app.url' => self::BASE,
            'sitemap.path' => $this->publicDir.'/sitemap.xml',
        ]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->publicDir);
        $this->app->usePublicPath($this->originalPublicPath);

        parent::tearDown();
    }

    public function test_command_generates_a_valid_sitemap_with_static_and_published_urls(): void
    {
        $updated = Carbon::parse('2026-10-01 12:00:00');
        Post::factory()->published()->create(['slug' => 'hello-world', 'updated_at' => $updated]);

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('Sitemap generated with 5 URL(s): '.self::BASE.'/sitemap.xml')
            ->assertSuccessful();

        $urls = $this->urls();

        $this->assertSame([
            self::BASE.'/',
            self::BASE.'/about',
            self::BASE.'/docs',
            self::BASE.'/blog',
            self::BASE.'/blog/hello-world',
        ], array_keys($urls));

        $this->assertSame($updated->toAtomString(), $urls[self::BASE.'/blog/hello-world']);
        $this->assertSame($updated->toAtomString(), $urls[self::BASE.'/blog'], 'The listing changes when its newest post does.');
        $this->assertNull($urls[self::BASE.'/about'], 'Static pages have no reliable lastmod.');
    }

    public function test_sitemap_follows_the_protocol_and_omits_unreliable_hints(): void
    {
        $this->artisan('sitemap:generate')->assertSuccessful();

        $xml = File::get($this->publicDir.'/sitemap.xml');
        $dom = new DOMDocument;
        $this->assertTrue($dom->loadXML($xml));
        $this->assertSame('urlset', $dom->documentElement->localName);
        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $dom->documentElement->namespaceURI);
        $this->assertStringNotContainsString('<changefreq>', $xml);
        $this->assertStringNotContainsString('<priority>', $xml);
        $this->assertSame('644', substr(sprintf('%o', fileperms($this->publicDir.'/sitemap.xml')), -3));
    }

    public function test_only_publicly_reachable_posts_are_included(): void
    {
        Post::factory()->published()->create(['slug' => 'public-post']);
        Post::factory()->create(['slug' => 'draft-post']);
        Post::factory()->published()->create(['slug' => 'trashed-post'])->delete();

        $this->artisan('sitemap:generate')->assertSuccessful();

        $urls = array_keys($this->urls());

        $this->assertContains(self::BASE.'/blog/public-post', $urls);
        $this->assertNotContains(self::BASE.'/blog/draft-post', $urls);
        $this->assertNotContains(self::BASE.'/blog/trashed-post', $urls);

        // Every listed post really is served publicly.
        $this->withoutVite();
        $this->get('/blog/public-post')->assertOk();
        $this->get('/blog/draft-post')->assertNotFound();
        $this->get('/blog/trashed-post')->assertNotFound();
    }

    public function test_private_auth_admin_api_and_query_string_urls_are_excluded(): void
    {
        $this->artisan('sitemap:generate')->assertSuccessful();

        foreach (array_keys($this->urls()) as $url) {
            $this->assertStringStartsWith(self::BASE.'/', $url);
            $this->assertStringNotContainsString('?', $url);
            $this->assertDoesNotMatchRegularExpression(
                '#/(login|register|forgot-password|reset-password|two-factor|verify-email|dashboard|settings|admin|api|language|logout|livewire)(/|$)#',
                $url,
            );
        }
    }

    public function test_duplicate_urls_are_listed_once(): void
    {
        config(['sitemap.static_routes' => ['home', 'blog', 'blog', 'home']]);

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->assertSame([self::BASE.'/', self::BASE.'/blog'], array_keys($this->urls()));
    }

    public function test_urls_use_the_configured_application_domain(): void
    {
        config(['app.url' => 'https://canonical.example.org/']);

        $this->artisan('sitemap:generate')->assertSuccessful();

        foreach (array_keys($this->urls()) as $url) {
            $this->assertStringStartsWith('https://canonical.example.org/', $url);
        }
        $this->assertStringNotContainsString('localhost', File::get($this->publicDir.'/sitemap.xml'));
    }

    public function test_large_sitemaps_are_split_behind_a_sitemap_index(): void
    {
        config(['sitemap.max_urls_per_file' => 2]);
        Post::factory()->published()->count(2)->create();
        File::put($this->publicDir.'/sitemap-9.xml', 'stale');

        $this->artisan('sitemap:generate')->assertSuccessful();

        $index = File::get($this->publicDir.'/sitemap.xml');
        $this->assertStringContainsString('<sitemapindex', $index);
        foreach ([1, 2, 3] as $n) {
            $this->assertStringContainsString('<loc>'.self::BASE."/sitemap-{$n}.xml</loc>", $index);
            $this->assertFileExists($this->publicDir."/sitemap-{$n}.xml");
        }
        $this->assertFileDoesNotExist($this->publicDir.'/sitemap-9.xml');

        // Collapsing back into one file removes the numbered files.
        config(['sitemap.max_urls_per_file' => 50000]);
        $this->artisan('sitemap:generate')->assertSuccessful();
        $this->assertStringContainsString('<urlset', File::get($this->publicDir.'/sitemap.xml'));
        $this->assertSame([$this->publicDir.'/sitemap.xml'], File::glob($this->publicDir.'/sitemap*.xml'));
    }

    public function test_failure_keeps_the_previous_sitemap_and_leaves_no_temp_files(): void
    {
        File::put($this->publicDir.'/sitemap.xml', '<previous/>');

        $this->mock(PostService::class)
            ->shouldReceive('lastPublishedModification')->andReturnNull()
            ->shouldReceive('publishedForSitemap')->andThrow(new RuntimeException('Database is down'));

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('the previous sitemap was left unchanged')
            ->expectsOutputToContain('Database is down')
            ->assertFailed();

        $this->assertSame('<previous/>', File::get($this->publicDir.'/sitemap.xml'));
        $this->assertSame([], File::glob($this->publicDir.'/.sitemap-*'), 'Temporary files were left behind.');
    }

    public function test_an_invalid_app_url_fails_without_writing(): void
    {
        config(['app.url' => 'not a url']);

        $this->artisan('sitemap:generate')->assertFailed();

        $this->assertFileDoesNotExist($this->publicDir.'/sitemap.xml');
    }

    public function test_a_development_domain_is_rejected_in_production(): void
    {
        config(['app.url' => 'http://majidastarter.test']);
        $this->app['env'] = 'production';

        try {
            $this->artisan('sitemap:generate')
                ->expectsOutputToContain('non-public host')
                ->assertFailed();
        } finally {
            $this->app['env'] = 'testing';
        }

        $this->assertFileDoesNotExist($this->publicDir.'/sitemap.xml');
    }

    public function test_an_unwritable_target_directory_fails_cleanly(): void
    {
        config(['sitemap.path' => $this->publicDir.'/missing/sitemap.xml']);

        $this->artisan('sitemap:generate')
            ->expectsOutputToContain('not writable')
            ->assertFailed();
    }

    public function test_robots_txt_declares_the_sitemap_once_generated(): void
    {
        $this->get('/robots.txt')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
            ->assertSee('User-agent: *')
            ->assertDontSee('Sitemap:');

        $this->artisan('sitemap:generate')->assertSuccessful();

        $this->get('/robots.txt')->assertSee('Sitemap: '.self::BASE.'/sitemap.xml');
    }

    public function test_generation_is_scheduled_without_overlapping(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'sitemap:generate'));

        $this->assertNotNull($event, 'sitemap:generate is not scheduled.');
        $this->assertTrue($event->withoutOverlapping);
        $this->assertSame(config('sitemap.schedule'), $event->expression);
    }

    public function test_publishing_changes_queue_a_single_regeneration(): void
    {
        config(['sitemap.regenerate_on_change' => true]);
        Queue::fake();

        $post = Post::factory()->create();
        $post->update(['excerpt' => ['en' => 'Still a draft']]);
        Queue::assertNothingPushed();

        $post->update(['status' => Post::STATUS_PUBLISHED]);
        Queue::assertPushed(GenerateSitemapJob::class, 1);

        // Further changes while a run is still pending are folded into it.
        $post->update(['excerpt' => ['en' => 'Edited after publishing']]);
        $post->delete();
        Queue::assertPushed(GenerateSitemapJob::class, 1);
    }

    public function test_deleting_a_published_post_queues_a_regeneration(): void
    {
        $post = Post::factory()->published()->create();

        config(['sitemap.regenerate_on_change' => true]);
        Queue::fake();

        $post->delete();

        Queue::assertPushed(GenerateSitemapJob::class, 1);
    }

    public function test_regeneration_on_change_can_be_disabled(): void
    {
        config(['sitemap.regenerate_on_change' => false]);
        Queue::fake();

        Post::factory()->published()->create();

        Queue::assertNothingPushed();
    }

    public function test_the_queued_job_generates_the_sitemap(): void
    {
        Post::factory()->published()->create(['slug' => 'queued-post']);

        GenerateSitemapJob::dispatchSync();

        $this->assertArrayHasKey(self::BASE.'/blog/queued-post', $this->urls());
    }

    /**
     * @return array<string, string|null> loc => lastmod, in document order
     */
    private function urls(): array
    {
        $dom = new DOMDocument;
        $dom->load($this->publicDir.'/sitemap.xml');
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('s', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $urls = [];
        foreach ($xpath->query('/s:urlset/s:url') as $node) {
            $loc = $xpath->evaluate('string(s:loc)', $node);
            $this->assertArrayNotHasKey($loc, $urls, "Duplicate URL {$loc}");
            $urls[$loc] = $xpath->evaluate('string(s:lastmod)', $node) ?: null;
        }

        return $urls;
    }
}

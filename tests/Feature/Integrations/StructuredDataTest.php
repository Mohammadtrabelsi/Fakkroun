<?php

namespace Tests\Feature\Integrations;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use App\Services\SettingService;
use App\Services\StructuredDataService;
use App\Services\TagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class StructuredDataTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<int, array<string, mixed>> the @graph nodes of every JSON-LD block on the page
     */
    private function jsonLd(TestResponse $response): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $response->getContent(), $matches);

        $this->assertNotEmpty($matches[1], 'No JSON-LD found on the page.');

        return collect($matches[1])
            ->map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR))
            ->flatMap(fn (array $data) => $data['@graph'] ?? [$data])
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $nodes
     * @return array<int, array<string, mixed>>
     */
    private function ofType(array $nodes, string $type): array
    {
        return array_values(array_filter($nodes, fn (array $node) => ($node['@type'] ?? null) === $type));
    }

    public function test_home_page_publishes_one_organization_and_one_website(): void
    {
        $setting = app(SettingService::class)->current();
        $setting->setTranslation('site_name', 'en', 'Acme Starter');
        $setting->support_email = 'help@example.com';
        $setting->save();

        $nodes = $this->jsonLd($this->get('/')->assertOk());

        $organization = $this->ofType($nodes, 'Organization');
        $website = $this->ofType($nodes, 'WebSite');

        $this->assertCount(1, $organization);
        $this->assertCount(1, $website);
        $this->assertSame('Acme Starter', $organization[0]['name']);
        $this->assertSame('help@example.com', $organization[0]['email']);
        $this->assertSame(route('home').'#organization', $organization[0]['@id']);
        $this->assertSame(['@id' => route('home').'#organization'], $website[0]['publisher']);
        $this->assertStringContainsString('/blog?q={search_term_string}', $website[0]['potentialAction']['target']);
        $this->assertSame([], $this->ofType($nodes, 'BreadcrumbList'));
    }

    public function test_post_page_publishes_a_blog_posting_and_breadcrumbs(): void
    {
        $author = User::factory()->create(['name' => 'Jane Writer']);
        $category = Category::factory()->create(['name' => ['en' => 'Guides']]);
        $post = Post::factory()->create([
            'title' => ['en' => 'Structured post'],
            'excerpt' => ['en' => 'A short summary.'],
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'user_id' => $author->id,
            'category_id' => $category->id,
        ]);
        app(TagService::class)->syncTo($post, ['Laravel', 'SEO']);

        $response = $this->get(route('blog.show', $post->slug))->assertOk();
        $nodes = $this->jsonLd($response);

        $article = $this->ofType($nodes, 'BlogPosting');
        $this->assertCount(1, $article);
        $this->assertSame('Structured post', $article[0]['headline']);
        $this->assertSame('A short summary.', $article[0]['description']);
        $this->assertSame('Jane Writer', $article[0]['author']['name']);
        $this->assertSame('Guides', $article[0]['articleSection']);
        $this->assertSame('Laravel, SEO', $article[0]['keywords']);
        $this->assertSame($post->publicUrl(), $article[0]['url']);
        $this->assertSame(['@id' => route('home').'#organization'], $article[0]['publisher']);

        $crumbs = $this->ofType($nodes, 'BreadcrumbList')[0]['itemListElement'];
        $this->assertSame([1, 2, 3], array_column($crumbs, 'position'));
        $this->assertSame([route('home'), route('blog'), $post->publicUrl()], array_column($crumbs, 'item'));

        $this->assertCount(1, $this->ofType($nodes, 'Organization'));

        $response->assertSee('<link rel="canonical" href="'.$post->publicUrl().'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee('<meta name="description" content="A short summary.">', false);
    }

    public function test_user_content_cannot_break_out_of_the_script_tag(): void
    {
        $post = Post::factory()->create([
            'title' => ['en' => 'Evil </script><script>alert(1)</script>'],
            'status' => Post::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);

        $response = $this->get(route('blog.show', $post->slug))->assertOk();

        $response->assertDontSee('<script>alert(1)</script>', false);
        $article = $this->ofType($this->jsonLd($response), 'BlogPosting');
        $this->assertStringContainsString('</script><script>alert(1)', $article[0]['headline']);
    }

    public function test_blog_and_static_pages_have_breadcrumbs(): void
    {
        foreach (['blog' => 'Blog', 'docs' => 'Docs'] as $route => $label) {
            $crumbs = $this->ofType($this->jsonLd($this->get(route($route))->assertOk()), 'BreadcrumbList');

            $this->assertCount(1, $crumbs, $route);
            $this->assertSame($label, $crumbs[0]['itemListElement'][1]['name']);
        }
    }

    public function test_structured_data_can_be_switched_off(): void
    {
        config(['seo.structured_data' => false]);

        $this->get('/')->assertOk()->assertDontSee('application/ld+json', false);
    }

    public function test_drafts_have_no_public_page_and_so_no_schema(): void
    {
        $draft = Post::factory()->create(['status' => Post::STATUS_DRAFT]);

        $this->get(route('blog.show', $draft->slug))->assertNotFound();
    }

    public function test_service_builds_breadcrumbs_in_order(): void
    {
        $list = app(StructuredDataService::class)->breadcrumbs(['A' => 'https://x.test/a', 'B' => 'https://x.test/b'])->toArray();

        $this->assertSame('BreadcrumbList', $list['@type']);
        $this->assertSame(['Home', 'A', 'B'], array_column($list['itemListElement'], 'name'));
    }
}

<?php

namespace Tests\Feature\Integrations;

use App\Models\Category;
use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PostService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SluggableTest extends TestCase
{
    use RefreshDatabase;

    public function test_slug_is_generated_from_the_english_title(): void
    {
        $post = Post::factory()->create(['title' => ['en' => 'Hello World Again', 'ar' => 'مرحبا'], 'slug' => null]);

        $this->assertSame('hello-world-again', $post->slug);
    }

    public function test_duplicate_titles_get_numbered_suffixes_starting_at_two(): void
    {
        $first = Post::factory()->create(['title' => ['en' => 'Same Title'], 'slug' => null]);
        $second = Post::factory()->create(['title' => ['en' => 'Same Title'], 'slug' => null]);
        $third = Post::factory()->create(['title' => ['en' => 'Same Title'], 'slug' => null]);

        $this->assertSame(['same-title', 'same-title-2', 'same-title-3'], [$first->slug, $second->slug, $third->slug]);
    }

    public function test_trashed_records_keep_their_slug_reserved(): void
    {
        $trashed = Post::factory()->create(['title' => ['en' => 'Reserved'], 'slug' => null]);
        $trashed->delete();

        $new = Post::factory()->create(['title' => ['en' => 'Reserved'], 'slug' => null]);

        $this->assertSame('reserved-2', $new->slug);

        $trashed->restore();
        $this->assertSame('reserved', $trashed->fresh()->slug);
    }

    public function test_a_supplied_slug_is_normalised_and_made_unique(): void
    {
        Post::factory()->create(['slug' => 'my-post']);

        $post = app(PostService::class)->create([
            'title' => ['en' => 'Anything'],
            'body' => ['en' => 'Body'],
            'slug' => 'My Post!!',
        ]);

        $this->assertSame('my-post-2', $post->slug);
    }

    public function test_changing_the_title_does_not_change_an_existing_slug(): void
    {
        $post = app(PostService::class)->create(['title' => ['en' => 'Original title'], 'body' => ['en' => 'x']]);

        app(PostService::class)->update($post, ['title' => ['en' => 'A completely new title'], 'slug' => $post->slug]);

        $this->assertSame('original-title', $post->fresh()->slug);
    }

    public function test_clearing_the_slug_regenerates_it_from_the_title(): void
    {
        $post = app(PostService::class)->create(['title' => ['en' => 'Original title'], 'body' => ['en' => 'x']]);

        app(PostService::class)->update($post, ['title' => ['en' => 'Fresh name'], 'slug' => null]);

        $this->assertSame('fresh-name', $post->fresh()->slug);
    }

    public function test_an_explicit_new_slug_replaces_the_old_one(): void
    {
        $post = app(PostService::class)->create(['title' => ['en' => 'Title'], 'body' => ['en' => 'x']]);

        app(PostService::class)->update($post, ['slug' => 'Brand New Slug']);

        $this->assertSame('brand-new-slug', $post->fresh()->slug);
    }

    public function test_unsluggable_titles_fall_back_to_the_model_name(): void
    {
        $post = Post::factory()->create(['title' => ['en' => '!!!'], 'slug' => null]);
        $other = Post::factory()->create(['title' => ['en' => '???'], 'slug' => null]);

        $this->assertSame(['post', 'post-2'], [$post->slug, $other->slug]);
    }

    public function test_title_in_another_locale_is_used_when_english_is_missing(): void
    {
        $post = Post::factory()->create(['title' => ['fr' => 'Bonjour le monde'], 'slug' => null]);

        $this->assertSame('bonjour-le-monde', $post->slug);
    }

    public function test_categories_use_the_same_rules(): void
    {
        $service = app(CategoryService::class);

        $a = $service->create(['name' => ['en' => 'News']]);
        $b = $service->create(['name' => ['en' => 'News']]);
        $service->update($a, ['name' => ['en' => 'Renamed'], 'slug' => $a->slug]);

        $this->assertSame('news-2', $b->slug);
        $this->assertSame('news', $a->fresh()->slug);
        $this->assertSame('category', Category::factory()->create(['name' => ['en' => '***'], 'slug' => null])->slug);
    }
}

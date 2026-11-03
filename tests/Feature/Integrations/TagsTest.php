<?php

namespace Tests\Feature\Integrations;

use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\PostService;
use App\Services\TagService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function publishedPost(array $tags = [], array $attributes = []): Post
    {
        $post = Post::factory()->create($attributes + ['status' => Post::STATUS_PUBLISHED, 'published_at' => now()]);
        app(TagService::class)->syncTo($post, $tags);

        return $post;
    }

    public function test_names_are_trimmed_deduplicated_and_capped(): void
    {
        $names = app(TagService::class)->normalize(['  Laravel  ', 'laravel', '', 'PHP   8', str_repeat('x', 80), ...range(1, 30)]);

        $this->assertSame('Laravel', $names[0]);
        $this->assertSame('PHP 8', $names[1]);
        $this->assertSame(TagService::MAX_LENGTH, mb_strlen($names[2]));
        $this->assertCount(TagService::MAX_PER_MODEL, $names);
    }

    public function test_post_service_creates_attaches_and_replaces_tags(): void
    {
        $posts = app(PostService::class);

        $post = $posts->create(['title' => ['en' => 'Tagged'], 'body' => ['en' => 'x'], 'tags' => ['Laravel', 'PHP']]);
        $this->assertEqualsCanonicalizing(['Laravel', 'PHP'], $post->tags->pluck('name')->all());

        $posts->update($post, ['tags' => ['php', 'Livewire']]);
        $this->assertEqualsCanonicalizing(['PHP', 'Livewire'], $post->fresh()->tags->pluck('name')->all());

        // Omitting "tags" leaves them untouched.
        $posts->update($post, ['title' => ['en' => 'Still tagged']]);
        $this->assertCount(2, $post->fresh()->tags);

        // "PHP" was reused, not duplicated.
        $this->assertSame(3, Tag::count());
    }

    public function test_tags_are_created_in_the_default_locale_whatever_the_ui_language(): void
    {
        app()->setLocale('ar');

        $post = app(PostService::class)->create(['title' => ['en' => 'x'], 'body' => ['en' => 'x'], 'tags' => ['News']]);

        $tag = $post->tags->first();
        $this->assertSame('News', $tag->getTranslation('name', 'en', false));
        $this->assertSame('news', $tag->getTranslation('slug', 'en', false));
    }

    public function test_tags_survive_a_soft_delete_and_are_detached_on_force_delete(): void
    {
        $post = $this->publishedPost(['Keep me']);
        $posts = app(PostService::class);

        $posts->delete($post);
        $posts->restore($post);
        $this->assertSame(['Keep me'], $post->fresh()->tags->pluck('name')->all());

        $posts->forceDelete($post->fresh());
        $this->assertDatabaseCount('taggables', 0);
        $this->assertDatabaseHas('tags', ['id' => Tag::first()->id]);
    }

    public function test_deleting_a_tag_removes_it_from_posts(): void
    {
        $post = $this->publishedPost(['Gone']);

        app(TagService::class)->delete(Tag::first());

        $this->assertCount(0, $post->fresh()->tags);
    }

    public function test_admin_can_tag_a_post_from_the_edit_form(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create(['user_id' => $admin->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.posts.edit', ['post' => $post])
            ->set('tags', ['Release', 'Laravel'])
            ->call('save')
            ->assertHasNoErrors();

        $this->assertEqualsCanonicalizing(['Release', 'Laravel'], $post->fresh()->tags->pluck('name')->all());
    }

    public function test_post_form_rejects_too_many_or_too_long_tags(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.posts.create')
            ->set('title.en', 'T')
            ->set('body.en', 'B')
            ->set('tags', array_map(fn ($i) => "tag {$i}", range(1, TagService::MAX_PER_MODEL + 1)))
            ->call('save')
            ->assertHasErrors('tags');

        Livewire::actingAs($admin)
            ->test('pages::admin.posts.create')
            ->set('title.en', 'T')
            ->set('body.en', 'B')
            ->set('tags', [str_repeat('x', TagService::MAX_LENGTH + 1)])
            ->call('save')
            ->assertHasErrors('tags.0');
    }

    public function test_tag_selector_adds_comma_separated_names_and_removes_them(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('admin.tag-selector')
            ->set('input', 'Laravel, php , Laravel')
            ->call('add')
            ->assertSet('tags', ['Laravel', 'php'])
            ->assertSet('input', '')
            ->call('remove', 0)
            ->assertSet('tags', ['php']);
    }

    public function test_admin_tag_page_creates_renames_translates_and_deletes(): void
    {
        $admin = User::factory()->admin()->create();

        $page = Livewire::actingAs($admin)->test('pages::admin.tags.index')
            ->set('name.en', 'Guides')
            ->call('save')
            ->assertHasNoErrors();

        $tag = Tag::firstOrFail();

        $page->call('edit', $tag->id)
            ->assertSet('name.en', 'Guides')
            ->set('name.en', 'How-tos')
            ->set('name.fr', 'Tutoriels')
            ->call('save')
            ->assertHasNoErrors();

        $tag->refresh();
        $this->assertSame('How-tos', $tag->getTranslation('name', 'en'));
        $this->assertSame('Tutoriels', $tag->getTranslation('name', 'fr'));
        $this->assertSame('how-tos', $tag->getTranslation('slug', 'en'));

        $page->call('deleteTag', $tag->id);
        $this->assertDatabaseCount('tags', 0);
    }

    public function test_admin_tag_page_rejects_duplicate_names(): void
    {
        $admin = User::factory()->admin()->create();
        app(TagService::class)->create(['en' => 'Laravel']);

        Livewire::actingAs($admin)->test('pages::admin.tags.index')
            ->set('name.en', 'LARAVEL')
            ->call('save')
            ->assertHasErrors('name.en');
    }

    public function test_tag_page_requires_the_manage_tags_permission(): void
    {
        $this->get('/admin/tags')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin/tags')->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get('/admin/tags')->assertOk()->assertSee('Tags');
    }

    public function test_admin_post_list_filters_by_tag(): void
    {
        $admin = User::factory()->admin()->create();
        $this->publishedPost(['Featured'], ['title' => ['en' => 'Tagged one']]);
        $this->publishedPost([], ['title' => ['en' => 'Untagged one']]);

        Livewire::actingAs($admin)->test('pages::admin.posts.index')
            ->set('tag', (string) Tag::first()->id)
            ->assertSee('Tagged one')
            ->assertDontSee('Untagged one');
    }

    public function test_blog_filters_by_tag_slug_and_shows_tag_links(): void
    {
        $this->publishedPost(['Release Notes'], ['title' => ['en' => 'Version two is out']]);
        $this->publishedPost([], ['title' => ['en' => 'Unrelated story']]);

        $this->get('/blog?tag=release-notes')
            ->assertOk()
            ->assertSee('Version two is out')
            ->assertDontSee('Unrelated story')
            ->assertSee('#Release Notes');

        $this->get('/blog')->assertSee(route('blog', ['tag' => 'release-notes']), false);
    }

    public function test_unknown_blog_tag_is_not_found(): void
    {
        $this->get('/blog?tag=does-not-exist')->assertNotFound();
    }

    public function test_api_accepts_and_filters_by_tags(): void
    {
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->postJson('/api/posts', [
            'title' => ['en' => 'From the API'],
            'body' => ['en' => 'Body'],
            'status' => Post::STATUS_DRAFT,
            'tags' => ['Api', 'Json'],
        ])->assertCreated()->assertJsonCount(2, 'tags');

        $id = $response->json('id');
        $this->publishedPost([], ['title' => ['en' => 'Other']]);

        $this->actingAs($admin)->getJson('/api/posts?tag='.Tag::where('name->en', 'Api')->value('id'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $id);

        // Updating without "tags" keeps them.
        $this->actingAs($admin)->putJson("/api/posts/{$id}", [
            'title' => ['en' => 'Renamed'],
            'body' => ['en' => 'Body'],
            'status' => Post::STATUS_DRAFT,
        ])->assertOk()->assertJsonCount(2, 'tags');

        $this->actingAs($admin)->postJson('/api/posts', [
            'title' => ['en' => 'Bad'],
            'body' => ['en' => 'Body'],
            'status' => Post::STATUS_DRAFT,
            'tags' => 'not-an-array',
        ])->assertUnprocessable()->assertJsonValidationErrors('tags');
    }
}

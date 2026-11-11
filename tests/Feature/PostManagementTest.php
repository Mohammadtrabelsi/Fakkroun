<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PostManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_can_view_posts_index(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/admin/posts')->assertOk();
    }

    public function test_non_admin_cannot_view_posts_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/admin/posts')->assertForbidden();
    }

    public function test_admin_can_create_a_post(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();

        Livewire::actingAs($admin)
            ->test('admin.posts.form')
            ->set('title.en', 'Hello World')
            ->set('title.ar', 'مرحبا')
            ->set('body.en', 'This is the body.')
            ->set('categoryId', $category->id)
            ->set('status', Post::STATUS_PUBLISHED)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.posts.index'));

        $post = Post::first();

        $this->assertNotNull($post);
        $this->assertSame('Hello World', $post->getTranslation('title', 'en'));
        $this->assertSame('hello-world', $post->slug);
        $this->assertSame($category->id, $post->category_id);
        $this->assertSame($admin->id, $post->user_id);
        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->published_at);
    }

    public function test_post_creation_requires_title_and_body(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('admin.posts.form')
            ->set('title.en', '')
            ->set('body.en', '')
            ->call('save')
            ->assertHasErrors(['title.en', 'body.en']);
    }

    public function test_draft_post_has_no_published_at(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test('admin.posts.form')
            ->set('title.en', 'Draft Post')
            ->set('body.en', 'Draft body.')
            ->set('status', Post::STATUS_DRAFT)
            ->call('save')
            ->assertHasNoErrors();

        $post = Post::first();

        $this->assertFalse($post->isPublished());
        $this->assertNull($post->published_at);
    }

    public function test_admin_can_update_a_post(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();

        Livewire::actingAs($admin)
            ->test('admin.posts.form', ['post' => $post])
            ->set('title.en', 'Changed Title')
            ->set('status', Post::STATUS_PUBLISHED)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('admin.posts.index'));

        $post->refresh();

        $this->assertSame('Changed Title', $post->getTranslation('title', 'en'));
        $this->assertTrue($post->isPublished());
        $this->assertNotNull($post->published_at);
    }

    public function test_admin_can_delete_a_post(): void
    {
        $admin = User::factory()->admin()->create();
        $post = Post::factory()->create();

        Livewire::actingAs($admin)
            ->test('pages::admin.posts.index')
            ->call('deletePost', $post->id)
            ->assertHasNoErrors();

        $this->assertSoftDeleted($post);
    }

    public function test_posts_index_can_be_filtered_by_status_and_category(): void
    {
        $admin = User::factory()->admin()->create();
        $category = Category::factory()->create();
        $otherCategory = Category::factory()->create();

        Post::factory()->create(['title' => ['en' => 'Wanted Post'], 'status' => Post::STATUS_PUBLISHED, 'category_id' => $category->id]);
        Post::factory()->create(['title' => ['en' => 'Other Category Post'], 'status' => Post::STATUS_PUBLISHED, 'category_id' => $otherCategory->id]);
        Post::factory()->create(['title' => ['en' => 'Draft Post Filtered'], 'status' => Post::STATUS_DRAFT, 'category_id' => $category->id]);

        Livewire::actingAs($admin)
            ->test('pages::admin.posts.index')
            ->set('status', Post::STATUS_PUBLISHED)
            ->assertSee('Wanted Post')
            ->assertSee('Other Category Post')
            ->assertDontSee('Draft Post Filtered')
            ->set('category', (string) $category->id)
            ->assertSee('Wanted Post')
            ->assertDontSee('Other Category Post')
            ->assertDontSee('Draft Post Filtered');
    }

    public function test_delete_button_asks_for_confirmation(): void
    {
        $admin = User::factory()->admin()->create();
        Post::factory()->create(['title' => ['en' => 'Confirm Post']]);

        $this->actingAs($admin)
            ->get('/admin/posts')
            ->assertSee('wire:confirm="Move this post to the trash?"', false);
    }

    public function test_deleting_category_nulls_post_category(): void
    {
        $category = Category::factory()->create();
        $post = Post::factory()->create(['category_id' => $category->id]);

        // Categories go to the trash first: the post keeps its category until it is really removed.
        $category->delete();
        $this->assertSame($category->id, $post->fresh()->category_id);

        $category->forceDelete();
        $this->assertNull($post->fresh()->category_id);
    }
}

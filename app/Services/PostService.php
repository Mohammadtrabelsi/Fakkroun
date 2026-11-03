<?php

namespace App\Services;

use App\Models\Post;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;

class PostService
{
    public const COVER_DIRECTORY = 'posts';

    /** @var array<int, string> */
    private const SORTABLE = ['title', 'published_at', 'created_at'];

    public function __construct(private readonly TagService $tags) {}

    /**
     * Slugs are generated and kept unique by the model (HasUniqueSlug). An
     * optional "tags" key holds tag names to attach.
     */
    public function create(array $data): Post
    {
        $tags = $this->pullTags($data);
        $data = $this->syncPublishedAt($data);

        return DB::transaction(function () use ($data, $tags): Post {
            $post = Post::create($data);

            if ($tags !== null) {
                $this->tags->syncTo($post, $tags);
            }

            return $post;
        });
    }

    /**
     * An unchanged slug is kept even if the title changes, so published URLs
     * stay stable; passing a null slug regenerates it from the title. When
     * the "tags" key is present the post's tags are replaced by those names.
     */
    public function update(Post $post, array $data): Post
    {
        $tags = $this->pullTags($data);
        $data = $this->syncPublishedAt($data, $post);

        // A replaced or removed cover must not leave an orphaned file behind.
        if (array_key_exists('cover_image', $data) && $data['cover_image'] !== $post->cover_image) {
            $this->deleteCover($post->cover_image);
        }

        DB::transaction(function () use ($post, $data, $tags): void {
            $post->fill($data);
            $post->save();

            if ($tags !== null) {
                $this->tags->syncTo($post, $tags);
            }
        });

        return $post;
    }

    /**
     * Publishes or unpublishes without touching the rest of the post (used by bulk actions).
     */
    public function setStatus(Post $post, string $status): Post
    {
        return $this->update($post, $this->syncPublishedAt(['status' => $status], $post) + [
            'title' => $post->getTranslations('title'),
            'slug' => $post->slug,
        ]);
    }

    /**
     * Soft delete: the post goes to the trash and keeps its cover so it can be restored.
     */
    public function delete(Post $post): void
    {
        $post->delete();
    }

    public function restore(Post $post): Post
    {
        $post->restore();

        return $post;
    }

    /**
     * Permanently removes the post and its cover image file.
     */
    public function forceDelete(Post $post): void
    {
        $this->deleteCover($post->cover_image);

        $post->forceDelete();
    }

    public function storeCover(UploadedFile $file): string
    {
        return $file->store(self::COVER_DIRECTORY, 'public');
    }

    /**
     * Admin listing. Supported filters: status (draft|published), category_id,
     * tag (tag id), trashed (only|with), sort (title|published_at|created_at)
     * and direction (asc|desc).
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchPaginated(?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Post::query()
            ->with(['category', 'author', 'tags'])
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(in_array($filters['status'] ?? null, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['category_id']), fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(! empty($filters['tag']), fn ($query) => $query->whereHas('tags', fn ($q) => $q->whereKey($filters['tag'])))
            ->when($search, fn ($query) => $this->matchSearch($query, $search))
            ->when($sort === 'title',
                fn ($query) => $query->orderBy('title->'.app()->getLocale(), $direction),
                fn ($query) => $query->orderBy($sort, $direction),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Public blog listing: published posts only, newest first, optionally
     * limited to one tag.
     */
    public function publishedPaginated(?string $search, int $perPage = 9, ?Tag $tag = null): LengthAwarePaginator
    {
        return Post::query()
            ->published()
            ->with(['category', 'author', 'tags'])
            ->when($tag, fn ($query) => $query->whereHas('tags', fn ($q) => $q->whereKey($tag->getKey())))
            ->when($search, fn ($query) => $this->matchSearch($query, $search))
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?Post
    {
        return Post::query()
            ->published()
            ->with(['category', 'author', 'tags'])
            ->where('slug', $slug)
            ->first();
    }

    /**
     * Items for the RSS feed (config/feed.php): the newest published posts,
     * i.e. exactly what /blog/{slug} serves. Trashed posts are excluded by
     * the soft-delete scope.
     *
     * @return Collection<int, Post>
     */
    public function feedItems(): Collection
    {
        return Post::query()
            ->published()
            ->whereNotNull('published_at')
            ->with(['category', 'author', 'tags'])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit((int) config('feed.limit', 20))
            ->get();
    }

    /**
     * Rows for the admin spreadsheet export, honouring the listing filters
     * (status, category_id, tag, trashed). Streamed in chunks so memory stays
     * flat regardless of the number of posts.
     *
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(?string $search, array $filters = []): LazyCollection
    {
        return Post::query()
            ->with(['category', 'author', 'tags'])
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(in_array($filters['status'] ?? null, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['category_id']), fn ($query) => $query->where('category_id', $filters['category_id']))
            ->when(! empty($filters['tag']), fn ($query) => $query->whereHas('tags', fn ($q) => $q->whereKey($filters['tag'])))
            ->when($search, fn ($query) => $this->matchSearch($query, $search))
            ->lazyById(500)
            ->map(fn (Post $post): array => [
                'id' => $post->id,
                'slug' => $post->slug,
                'title_en' => $post->getTranslation('title', 'en', false),
                'title_ar' => $post->getTranslation('title', 'ar', false),
                'status' => $post->status,
                'category' => $post->category?->name,
                'tags' => $post->tags->pluck('name')->implode(', '),
                'author_email' => $post->author?->email,
                'published_at' => $post->published_at?->toIso8601String(),
                'created_at' => $post->created_at?->toIso8601String(),
                'updated_at' => $post->updated_at?->toIso8601String(),
            ]);
    }

    /**
     * Every publicly reachable post (the same set findPublishedBySlug() serves),
     * streamed in id order with only the columns a sitemap needs, so memory
     * stays flat however many posts there are.
     *
     * @return LazyCollection<int, Post>
     */
    public function publishedForSitemap(int $chunkSize = 500): LazyCollection
    {
        return Post::query()
            ->published()
            ->select(['id', 'slug', 'updated_at'])
            ->lazyById($chunkSize);
    }

    /**
     * When the most recently changed published post was last modified, or null if none.
     */
    public function lastPublishedModification(): ?CarbonInterface
    {
        $latest = Post::query()->published()->max('updated_at');

        return $latest ? Carbon::parse($latest) : null;
    }

    public function stats(): array
    {
        // Single aggregate query instead of three separate COUNT statements.
        $counts = Post::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as published', [Post::STATUS_PUBLISHED])
            ->selectRaw('sum(case when status = ? then 1 else 0 end) as drafts', [Post::STATUS_DRAFT])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'published' => (int) ($counts->published ?? 0),
            'drafts' => (int) ($counts->drafts ?? 0),
            'trashed' => Post::onlyTrashed()->count(),
        ];
    }

    /**
     * Matches the search term against the slug and the title/excerpt in every
     * configured locale, so a title in any language is findable.
     */
    protected function matchSearch($query, string $search)
    {
        return $query->where(function ($q) use ($search) {
            $q->where('slug', 'like', "%{$search}%");

            foreach (array_keys(config('app.available_locales')) as $locale) {
                $q->orWhere("title->{$locale}", 'like', "%{$search}%")
                    ->orWhere("excerpt->{$locale}", 'like', "%{$search}%");
            }
        });
    }

    /**
     * Ensure a post has a publish timestamp when published, and clears it otherwise.
     */
    protected function syncPublishedAt(array $data, ?Post $post = null): array
    {
        if (! array_key_exists('status', $data)) {
            return $data;
        }

        if ($data['status'] === Post::STATUS_PUBLISHED) {
            $data['published_at'] = $post->published_at ?? now();
        } else {
            $data['published_at'] = null;
        }

        return $data;
    }

    protected function deleteCover(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Removes the "tags" key from the payload. Null means "leave tags alone".
     *
     * @return array<int, string>|null
     */
    protected function pullTags(array &$data): ?array
    {
        if (! array_key_exists('tags', $data)) {
            return null;
        }

        $tags = (array) ($data['tags'] ?? []);
        unset($data['tags']);

        return $tags;
    }
}

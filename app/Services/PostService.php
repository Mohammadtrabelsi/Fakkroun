<?php

namespace App\Services;

use App\Models\Post;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;

class PostService
{
    public const COVER_DIRECTORY = 'posts';

    /** @var array<int, string> */
    private const SORTABLE = ['title', 'published_at', 'created_at'];

    public function create(array $data): Post
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title'] ?? null);
        $data = $this->syncPublishedAt($data);

        return Post::create($data);
    }

    public function update(Post $post, array $data): Post
    {
        $data['slug'] = $this->uniqueSlug($data['slug'] ?? null, $data['title'] ?? null, $post);
        $data = $this->syncPublishedAt($data, $post);

        // A replaced or removed cover must not leave an orphaned file behind.
        if (array_key_exists('cover_image', $data) && $data['cover_image'] !== $post->cover_image) {
            $this->deleteCover($post->cover_image);
        }

        $post->fill($data);
        $post->save();

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
     * trashed (only|with), sort (title|published_at|created_at) and direction (asc|desc).
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchPaginated(?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Post::query()
            ->with(['category', 'author'])
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(in_array($filters['status'] ?? null, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true), fn ($query) => $query->where('status', $filters['status']))
            ->when(! empty($filters['category_id']), fn ($query) => $query->where('category_id', $filters['category_id']))
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
     * Public blog listing: published posts only, newest first.
     */
    public function publishedPaginated(?string $search, int $perPage = 9): LengthAwarePaginator
    {
        return Post::query()
            ->published()
            ->with(['category', 'author'])
            ->when($search, fn ($query) => $this->matchSearch($query, $search))
            ->orderByDesc('published_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function findPublishedBySlug(string $slug): ?Post
    {
        return Post::query()
            ->published()
            ->with(['category', 'author'])
            ->where('slug', $slug)
            ->first();
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
     * Build a unique slug, falling back to the title when none is supplied.
     * Trashed posts still hold their slug, so restoring one can never collide.
     */
    protected function uniqueSlug(?string $slug, string|array|null $title, ?Post $ignore = null): string
    {
        $source = $slug ?: (is_array($title) ? ($title['en'] ?? reset($title) ?: '') : (string) $title);
        $base = Str::slug($source) ?: 'post';
        $candidate = $base;
        $suffix = 2;

        while (Post::withTrashed()
            ->where('slug', $candidate)
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()
        ) {
            $candidate = "{$base}-{$suffix}";
            $suffix++;
        }

        return $candidate;
    }
}

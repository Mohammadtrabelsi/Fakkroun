<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\TracksUserActions;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Feed\Feedable;
use Spatie\Feed\FeedItem;
use Spatie\Tags\HasTags;
use Spatie\Translatable\HasTranslations;

class Post extends Model implements Feedable
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, HasTags, HasTranslations, HasUniqueSlug, LogsActivity, SoftDeletes, TracksUserActions;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'title',
        'excerpt',
        'body',
        'cover_image',
        'slug',
        'status',
        'category_id',
        'user_id',
        'published_at',
    ];

    public array $translatable = [
        'title',
        'excerpt',
        'body',
    ];

    protected $attributes = [
        'status' => self::STATUS_DRAFT,
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * Replaces HasTags' boot logic, which detaches tags on every delete: here
     * tags survive a soft delete, so a restored post keeps them, and are only
     * detached when the post is deleted for good.
     */
    public static function bootHasTags(): void
    {
        static::created(function (self $post): void {
            if ($post->queuedTags !== []) {
                $post->attachTags($post->queuedTags);
                $post->queuedTags = [];
            }
        });

        static::forceDeleted(fn (self $post) => $post->tags()->detach());
    }

    /**
     * @return array<string, string>
     */
    public static function statuses(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_PUBLISHED => 'Published',
        ];
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED;
    }

    /**
     * Public URL of the cover image, or null when the post has none.
     */
    public function coverImageUrl(): ?string
    {
        return $this->cover_image
            ? Storage::disk('public')->url($this->cover_image)
            : null;
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', self::STATUS_PUBLISHED);
    }

    /**
     * Public URL of the post (only meaningful once it is published).
     */
    public function publicUrl(): string
    {
        return route('blog.show', $this->slug);
    }

    /**
     * Short plain-text summary: the excerpt, or the start of the body.
     */
    public function summary(int $limit = 200): string
    {
        $text = $this->excerpt ?: $this->body;

        return Str::limit(trim(preg_replace('/\s+/', ' ', strip_tags((string) $text))), $limit);
    }

    public function toFeedItem(): FeedItem
    {
        return FeedItem::create()
            ->id($this->publicUrl())
            ->title($this->title)
            ->summary($this->summary(500))
            ->updated($this->updated_at)
            ->link($this->publicUrl())
            ->authorName($this->author?->name ?? config('app.name'))
            ->category(...array_filter([$this->category?->name, ...$this->tags->pluck('name')->all()]));
    }

    protected function slugSourceAttribute(): string
    {
        return 'title';
    }

    protected function slugFallback(): string
    {
        return 'post';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'slug', 'status', 'category_id', 'published_at'])
            ->logOnlyDirty()
            ->useLogName('posts');
    }
}

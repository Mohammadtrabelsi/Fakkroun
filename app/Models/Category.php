<?php

namespace App\Models;

use App\Enums\ValidationPolicy;
use App\Models\Concerns\HasUniqueSlug;
use App\Models\Concerns\TracksUserActions;
use Database\Factories\CategoryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;
use Spatie\Translatable\HasTranslations;

/**
 * A quiz topic (and blog category). Categories nest one level deep: a row
 * with a parent_id is a subcategory.
 *
 * @property string $slug
 * @property int|null $parent_id
 * @property ValidationPolicy $validation_policy
 */
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations, HasUniqueSlug, LogsActivity, SoftDeletes, TracksUserActions;

    protected $fillable = [
        'name',
        'description',
        'slug',
        'is_active',
        'parent_id',
        'sort_order',
        'icon',
        'color',
        'validation_policy',
        'requires_reference',
        'allow_community_submissions',
        'is_priority',
    ];

    public array $translatable = [
        'name',
        'description',
    ];

    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
        'validation_policy' => 'admin',
        'requires_reference' => false,
        'allow_community_submissions' => true,
        'is_priority' => false,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
            'validation_policy' => ValidationPolicy::class,
            'requires_reference' => 'boolean',
            'allow_community_submissions' => 'boolean',
            'is_priority' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Users assigned to moderate this category (and its subcategories).
     *
     * @return BelongsToMany<User, $this>
     */
    public function moderators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'category_moderator')->withTimestamps();
    }

    public function isSubcategory(): bool
    {
        return $this->parent_id !== null;
    }

    /**
     * Top-level categories only.
     *
     * @param  Builder<Category>  $query
     */
    public function scopeRoots(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    /**
     * @param  Builder<Category>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    protected function slugSourceAttribute(): string
    {
        return 'name';
    }

    protected function slugFallback(): string
    {
        return 'category';
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'name', 'description', 'slug', 'is_active', 'parent_id', 'sort_order', 'icon', 'color',
                'validation_policy', 'requires_reference', 'allow_community_submissions',
            ])
            ->logOnlyDirty()
            ->useLogName('categories');
    }
}

<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

/**
 * URL slugs backed by spatie/laravel-sluggable, with the rules the public
 * URLs depend on:
 *
 * - A new record gets a slug from its source attribute (in the slug locale)
 *   unless one is supplied; a supplied slug is normalised with Str::slug().
 * - Slugs are unique across the table, trashed rows included, so restoring a
 *   record can never collide. Duplicates get "-2", "-3", … appended.
 * - An existing record keeps its slug when only other fields (e.g. the title)
 *   change, so published URLs never move by accident. Setting a new slug, or
 *   clearing it to regenerate from the source, is the only way to change it.
 *
 * Models define slugSourceAttribute() (a translatable attribute) and
 * slugFallback() (used when the source has no sluggable characters).
 */
trait HasUniqueSlug
{
    use HasSlug;

    /** Locale whose value the slug is generated from. */
    public const SLUG_LOCALE = 'en';

    abstract protected function slugSourceAttribute(): string;

    abstract protected function slugFallback(): string;

    public function getSlugOptions(): SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom(fn (self $model): string => $model->slugSource())
            ->saveSlugsTo('slug')
            ->slugsShouldBeNoLongerThan(240)
            ->startSlugSuffixFrom(2)
            ->skipGenerateWhen(fn (): bool => $this->exists && ! $this->isDirty('slug'));
    }

    /**
     * Normalises any slug written to the model; an empty result becomes null
     * so that it is regenerated from the source attribute.
     *
     * @return Attribute<string|null, string|null>
     */
    protected function slug(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value): ?string => $value,
            set: fn (?string $value): ?string => Str::slug((string) $value) ?: null,
        );
    }

    /**
     * The slug locale's value, else the first non-empty translation, else the fallback.
     */
    protected function slugSource(): string
    {
        $attribute = $this->slugSourceAttribute();
        $translations = array_filter($this->getTranslations($attribute), fn ($value) => filled($value));
        $source = (string) ($translations[self::SLUG_LOCALE] ?? reset($translations) ?: '');

        return Str::slug($source) !== '' ? $source : $this->slugFallback();
    }
}

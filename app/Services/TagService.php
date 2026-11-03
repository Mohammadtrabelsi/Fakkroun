<?php

namespace App\Services;

use App\Models\Tag;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Tag management (spatie/laravel-tags). Tags are created in the default
 * content locale (see Tag::getLocale()) and may be translated per locale.
 */
class TagService
{
    /** Longest tag name accepted. */
    public const MAX_LENGTH = 50;

    /** Most tags a single model may carry. */
    public const MAX_PER_MODEL = 20;

    /**
     * Trims, collapses whitespace, drops blanks and case-insensitive
     * duplicates, and caps the list, keeping the first spelling given.
     *
     * @param  iterable<mixed>  $names
     * @return array<int, string>
     */
    public function normalize(iterable $names): array
    {
        $unique = [];

        foreach ($names as $name) {
            $name = Str::limit(trim((string) preg_replace('/\s+/u', ' ', (string) $name)), self::MAX_LENGTH, '');

            if ($name !== '' && ! isset($unique[mb_strtolower($name)])) {
                $unique[mb_strtolower($name)] = $name;
            }
        }

        return array_slice(array_values($unique), 0, self::MAX_PER_MODEL);
    }

    /**
     * Replaces a taggable model's tags with the given names, creating missing tags.
     *
     * @param  Model  $model  a model using Spatie\Tags\HasTags
     * @param  iterable<mixed>  $names
     */
    public function syncTo(Model $model, iterable $names): void
    {
        $model->syncTags($this->normalize($names));
    }

    /**
     * Tag id => name (current locale, falling back to the default), for selects.
     *
     * @return array<int, string>
     */
    public function options(): array
    {
        return Tag::query()
            ->orderBy('name->'.Tag::getLocale())
            ->get()
            ->mapWithKeys(fn (Tag $tag) => [$tag->id => $tag->name])
            ->all();
    }

    /**
     * Names in the default locale, for the tag selector's suggestions.
     *
     * @return array<int, string>
     */
    public function suggestions(?string $term = null, int $limit = 10): array
    {
        return Tag::query()
            ->when(filled($term), fn ($query) => $query->containing((string) $term))
            ->orderBy('name->'.Tag::getLocale())
            ->limit($limit)
            ->get()
            ->map(fn (Tag $tag) => $tag->getTranslation('name', Tag::getLocale()))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Resolves a public tag slug (default locale) to a tag, if one exists.
     */
    public function findBySlug(string $slug): ?Tag
    {
        return Tag::query()->where('slug->'.Tag::getLocale(), $slug)->first();
    }

    /**
     * Admin listing with the number of posts using each tag (trashed posts excluded).
     */
    public function searchPaginated(?string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Tag::query()
            ->withCount('posts')
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                foreach (array_keys(config('app.available_locales')) as $locale) {
                    $q->orWhere("name->{$locale}", 'like', "%{$search}%");
                }
            }))
            ->orderBy('name->'.Tag::getLocale())
            ->orderBy('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, string|null>  $names  locale => name; the default locale is required
     */
    public function create(array $names): Tag
    {
        return Tag::create(['name' => $this->translations($names)]);
    }

    /**
     * Updates the translated names; slugs are regenerated from them by the
     * tag model, so public /blog?tag= links follow the default-locale name.
     *
     * @param  array<string, string|null>  $names
     */
    public function update(Tag $tag, array $names): Tag
    {
        $tag->replaceTranslations('name', $this->translations($names));
        $tag->save();

        return $tag;
    }

    /**
     * Deletes the tag; its links to posts are removed by the foreign key cascade.
     */
    public function delete(Tag $tag): void
    {
        $tag->delete();
    }

    /**
     * Whether another tag already uses this name in the default locale.
     */
    public function nameTaken(string $name, ?Tag $ignore = null): bool
    {
        return Tag::query()
            ->whereRaw('lower('.Tag::query()->getQuery()->getGrammar()->wrap('name->'.Tag::getLocale()).') = ?', [mb_strtolower(trim($name))])
            ->when($ignore, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();
    }

    /**
     * @param  array<string, string|null>  $names
     * @return array<string, string>
     */
    private function translations(array $names): array
    {
        return collect($names)
            ->only(array_keys(config('app.available_locales')))
            ->map(fn ($name) => trim((string) $name))
            ->filter(fn (string $name) => $name !== '')
            ->all();
    }
}

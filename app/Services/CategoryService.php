<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\ValidationPolicy;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;
use Spatie\Activitylog\Models\Activity;

class CategoryService
{
    /** @var array<int, string> */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * Slugs are generated and kept unique by the model (HasUniqueSlug).
     */
    public function create(array $data): Category
    {
        $this->assertValidParent(null, $data['parent_id'] ?? null);

        return Category::create($data);
    }

    /**
     * An unchanged slug is kept even if the name changes; passing a null slug
     * regenerates it from the name.
     */
    public function update(Category $category, array $data): Category
    {
        if (array_key_exists('parent_id', $data)) {
            $this->assertValidParent($category, $data['parent_id']);
        }

        $category->fill($data);
        $category->save();

        return $category;
    }

    /**
     * Categories nest one level deep (category → subcategory). A category
     * cannot be its own parent, cannot sit under a subcategory, and cannot
     * become a subcategory while it still has subcategories of its own.
     */
    protected function assertValidParent(?Category $category, mixed $parentId): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($category !== null && (int) $parentId === $category->id) {
            throw new DomainActionException(__('categories.parent_self'));
        }

        $parent = Category::query()->find($parentId);

        if ($parent === null) {
            throw new DomainActionException(__('categories.parent_missing'));
        }

        if ($parent->parent_id !== null) {
            throw new DomainActionException(__('categories.parent_too_deep'));
        }

        if ($category !== null && $category->children()->exists()) {
            throw new DomainActionException(__('categories.parent_has_children'));
        }
    }

    /**
     * Rewrites sort_order for the given ids, in the order given (siblings).
     *
     * @param  array<int, int|string>  $orderedIds
     */
    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds): void {
            foreach (array_values($orderedIds) as $position => $id) {
                Category::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }

    /**
     * Top-level categories with their subcategories, in display order.
     *
     * @return Collection<int, Category>
     */
    public function tree(bool $activeOnly = false): Collection
    {
        return Category::query()
            ->roots()
            ->when($activeOnly, fn ($query) => $query->where('is_active', true))
            ->with(['children' => fn ($query) => $query->when($activeOnly, fn ($q) => $q->where('is_active', true))])
            ->ordered()
            ->get();
    }

    /**
     * Top-level categories a subcategory may be filed under.
     *
     * @return array<int, string>
     */
    public function parentOptions(?Category $except = null): array
    {
        return Category::query()
            ->roots()
            ->when($except, fn ($query) => $query->whereKeyNot($except->id))
            ->ordered()
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    /**
     * Replaces the categories a moderator is assigned to (spec §3.3).
     *
     * @param  array<int, int|string>  $categoryIds
     */
    public function syncModeratedCategories(User $moderator, array $categoryIds): void
    {
        if (! $moderator->hasRole(UserRole::Moderator->value)) {
            throw new DomainActionException(__('categories.not_a_moderator'));
        }

        $ids = Category::query()->whereKey($categoryIds)->pluck('id')->all();

        $moderator->moderatedCategories()->sync($ids);

        $actor = auth()->user();

        (new Activity)->forceFill([
            'log_name' => 'categories',
            'description' => 'moderator categories updated',
            'event' => 'moderators',
            'subject_type' => $moderator->getMorphClass(),
            'subject_id' => $moderator->getKey(),
            'causer_type' => $actor?->getMorphClass(),
            'causer_id' => $actor?->getKey(),
            'properties' => ['category_ids' => $ids],
        ])->save();
    }

    /**
     * Ids of the categories a user may moderate: the assigned categories plus
     * the subcategories of any assigned top-level category.
     *
     * @return array<int, int>
     */
    public function moderatedCategoryIds(User $user): array
    {
        $assigned = $user->moderatedCategories()->pluck('categories.id')->all();

        if ($assigned === []) {
            return [];
        }

        $children = Category::query()->whereIn('parent_id', $assigned)->pluck('id')->all();

        return array_values(array_unique(array_map('intval', [...$assigned, ...$children])));
    }

    public function setActive(Category $category, bool $active): Category
    {
        $category->update(['is_active' => $active]);

        return $category;
    }

    /**
     * Soft delete: the category goes to the trash and can be restored.
     */
    public function delete(Category $category): void
    {
        $category->delete();
    }

    public function restore(Category $category): Category
    {
        $category->restore();

        return $category;
    }

    public function forceDelete(Category $category): void
    {
        $category->forceDelete();
    }

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        return Category::query()
            ->orderBy('slug')
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $category->name])
            ->all();
    }

    /**
     * Admin listing. Supported filters: status (active|inactive), trashed (only|with),
     * sort (name|created_at) and direction (asc|desc).
     *
     * @param  array<string, mixed>  $filters
     */
    public function searchPaginated(?string $search, int $perPage = 10, array $filters = []): LengthAwarePaginator
    {
        $sort = in_array($filters['sort'] ?? null, self::SORTABLE, true) ? $filters['sort'] : 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        return Category::query()
            ->withCount(['posts', 'children'])
            ->with('parent')
            ->when(($filters['trashed'] ?? null) === 'only', fn ($query) => $query->onlyTrashed())
            ->when(($filters['trashed'] ?? null) === 'with', fn ($query) => $query->withTrashed())
            ->when(($filters['status'] ?? null) === 'active', fn ($query) => $query->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('slug', 'like', "%{$search}%");

                foreach (array_keys(config('app.available_locales')) as $locale) {
                    $q->orWhere("name->{$locale}", 'like', "%{$search}%");
                }
            }))
            ->when($sort === 'name',
                fn ($query) => $query->orderBy('name->'.app()->getLocale(), $direction),
                fn ($query) => $query->orderBy($sort, $direction),
            )
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function stats(): array
    {
        // Single aggregate query instead of three separate COUNT statements.
        $counts = Category::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_active = ? then 1 else 0 end) as active', [true])
            ->selectRaw('sum(case when is_active = ? then 1 else 0 end) as inactive', [false])
            ->toBase()
            ->first();

        return [
            'total' => (int) ($counts->total ?? 0),
            'active' => (int) ($counts->active ?? 0),
            'inactive' => (int) ($counts->inactive ?? 0),
            'trashed' => Category::onlyTrashed()->count(),
        ];
    }

    /**
     * Rows for the spreadsheet export, in the same column layout the import
     * accepts, so an exported file can be edited and imported back.
     *
     * @return LazyCollection<int, array<string, mixed>>
     */
    public function exportRows(): LazyCollection
    {
        return Category::query()
            ->withCount('posts')
            ->with('parent:id,slug')
            ->lazyById(500)
            ->map(fn (Category $category): array => $this->exportRow($category));
    }

    /**
     * @return array<string, mixed>
     */
    protected function exportRow(Category $category): array
    {
        $row = ['slug' => $category->slug];

        foreach ($this->importLocales() as $locale) {
            $row["name_{$locale}"] = $category->getTranslation('name', $locale, false);
        }

        foreach ($this->importLocales() as $locale) {
            $row["description_{$locale}"] = $category->getTranslation('description', $locale, false);
        }

        return $row + [
            'parent_slug' => $category->parent?->slug,
            'sort_order' => $category->sort_order,
            'icon' => $category->icon,
            'color' => $category->color,
            'validation_policy' => $category->validation_policy->value,
            'requires_reference' => $category->requires_reference ? 1 : 0,
            'allow_community_submissions' => $category->allow_community_submissions ? 1 : 0,
            'is_active' => $category->is_active ? 1 : 0,
            'posts_count' => $category->posts_count,
        ];
    }

    /**
     * Validation rules for one imported row (see exportRows() for the columns).
     *
     * @return array<string, mixed>
     */
    public function importRules(): array
    {
        $rules = [
            'slug' => ['nullable', 'string', 'max:240', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
            'is_active' => ['nullable', 'in:0,1,true,false,yes,no'],
            'parent_slug' => ['nullable', 'string', 'max:240'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'validation_policy' => ['nullable', 'in:'.implode(',', ValidationPolicy::values())],
            'requires_reference' => ['nullable', 'in:0,1,true,false,yes,no'],
            'allow_community_submissions' => ['nullable', 'in:0,1,true,false,yes,no'],
        ];

        foreach ($this->importLocales() as $locale) {
            $rules["name_{$locale}"] = [$locale === 'en' ? 'required' : 'nullable', 'string', 'max:255'];
            $rules["description_{$locale}"] = ['nullable', 'string', 'max:5000'];
        }

        return $rules;
    }

    /**
     * Columns an import file must contain.
     *
     * @return array<int, string>
     */
    public function importRequiredHeaders(): array
    {
        return ['name_en'];
    }

    /**
     * Creates or updates (matched by slug) a category from a validated row.
     * Returns true when a category was created. A slug held by a trashed
     * category is rejected rather than silently restoring it.
     *
     * @param  array<string, mixed>  $row
     */
    public function importRow(array $row): bool
    {
        $existing = filled($row['slug'] ?? null)
            ? Category::withTrashed()->where('slug', $row['slug'])->first()
            : null;

        if ($existing?->trashed()) {
            throw new DomainActionException(__('imports.slug_trashed', ['slug' => $row['slug']]));
        }

        $data = ['name' => [], 'description' => []];

        foreach ($this->importLocales() as $locale) {
            if (array_key_exists("name_{$locale}", $row)) {
                $data['name'][$locale] = (string) ($row["name_{$locale}"] ?? '');
            }

            if (array_key_exists("description_{$locale}", $row)) {
                $data['description'][$locale] = (string) ($row["description_{$locale}"] ?? '');
            }
        }

        foreach (['is_active', 'requires_reference', 'allow_community_submissions'] as $flag) {
            if (array_key_exists($flag, $row) && $row[$flag] !== null && $row[$flag] !== '') {
                $data[$flag] = in_array(strtolower((string) $row[$flag]), ['1', 'true', 'yes'], true);
            }
        }

        foreach (['icon', 'color', 'validation_policy'] as $field) {
            if (filled($row[$field] ?? null)) {
                $data[$field] = (string) $row[$field];
            }
        }

        if (filled($row['sort_order'] ?? null)) {
            $data['sort_order'] = (int) $row['sort_order'];
        }

        // Parents are matched by slug and must already exist (exports list
        // them first); an empty parent_slug makes the category top-level.
        if (array_key_exists('parent_slug', $row)) {
            $parentSlug = $row['parent_slug'];
            $parent = filled($parentSlug) ? Category::query()->where('slug', $parentSlug)->first() : null;

            if (filled($parentSlug) && $parent === null) {
                throw new DomainActionException(__('categories.parent_missing'));
            }

            $data['parent_id'] = $parent?->id;
        }

        if ($existing) {
            // Keep translations the file does not mention.
            $data['name'] = array_filter($data['name'], 'filled') + $existing->getTranslations('name');
            $data['description'] = $data['description'] + $existing->getTranslations('description');
            $this->update($existing, $data);

            return false;
        }

        $this->create($data + ['slug' => $row['slug'] ?? null]);

        return true;
    }

    /**
     * @return array<int, string>
     */
    protected function importLocales(): array
    {
        return array_keys(config('app.available_locales'));
    }
}

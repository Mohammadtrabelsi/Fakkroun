<?php

namespace App\Services;

use App\Exceptions\DomainActionException;
use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\LazyCollection;

class CategoryService
{
    /** @var array<int, string> */
    private const SORTABLE = ['name', 'created_at'];

    /**
     * Slugs are generated and kept unique by the model (HasUniqueSlug).
     */
    public function create(array $data): Category
    {
        return Category::create($data);
    }

    /**
     * An unchanged slug is kept even if the name changes; passing a null slug
     * regenerates it from the name.
     */
    public function update(Category $category, array $data): Category
    {
        $category->fill($data);
        $category->save();

        return $category;
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
            ->withCount('posts')
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

        if (array_key_exists('is_active', $row) && $row['is_active'] !== null) {
            $data['is_active'] = in_array(strtolower((string) $row['is_active']), ['1', 'true', 'yes'], true);
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

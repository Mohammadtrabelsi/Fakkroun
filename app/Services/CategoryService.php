<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

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
}

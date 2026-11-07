<?php

use App\Models\Category;
use App\Services\CategoryService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Categories')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** Ability each bulk action requires on every selected category. */
    private const BULK_ABILITIES = [
        'activate' => 'update',
        'deactivate' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'forceDelete',
    ];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    /** all | active | inactive | trashed */
    #[Url(as: 'status', history: true)]
    public string $status = 'all';

    /** name | created_at */
    #[Url(as: 'sort', history: true)]
    public string $sort = 'created_at';

    #[Url(as: 'dir', history: true)]
    public string $direction = 'desc';

    /** @var array<int, string> */
    public array $selected = [];

    public string $bulkAction = '';

    public ?string $notice = null;

    public function mount(): void
    {
        $this->authorize('viewAny', Category::class);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'sort', 'direction'], true)) {
            $this->resetPage();
            $this->selected = [];
        }
    }

    public function sortBy(string $column): void
    {
        $this->direction = $this->sort === $column && $this->direction === 'asc' ? 'desc' : 'asc';
        $this->sort = $column;
    }

    public function selectPage(): void
    {
        $this->selected = $this->categories->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function updatedActive(): void
    {
        $this->resetPage();
    }

    public function deleteCategory(int $categoryId, CategoryService $categories): void
    {
        $category = Category::findOrFail($categoryId);
        $this->authorize('delete', $category);

        $categories->delete($category);

        $this->toast('Category moved to the trash.');
    }

    public function restoreCategory(int $categoryId, CategoryService $categories): void
    {
        $category = Category::onlyTrashed()->findOrFail($categoryId);
        $this->authorize('restore', $category);

        $categories->restore($category);

        $this->toast('Category restored.');
    }

    public function forceDeleteCategory(int $categoryId, CategoryService $categories): void
    {
        $category = Category::onlyTrashed()->findOrFail($categoryId);
        $this->authorize('forceDelete', $category);

        $categories->forceDelete($category);

        $this->toast('Category permanently deleted.');
    }

    public function applyBulk(CategoryService $categories): void
    {
        $action = $this->bulkAction;

        if (! array_key_exists($action, self::BULK_ABILITIES) || $this->selected === []) {
            return;
        }

        $ability = self::BULK_ABILITIES[$action];
        $user = auth()->user();
        $done = 0;

        foreach (Category::withTrashed()->whereIn('id', $this->selected)->get() as $category) {
            if ($user->cannot($ability, $category)) {
                continue;
            }

            if ($category->trashed() && in_array($action, ['activate', 'deactivate', 'delete'], true)) {
                continue;
            }

            match ($action) {
                'activate' => $categories->setActive($category, true),
                'deactivate' => $categories->setActive($category, false),
                'delete' => $categories->delete($category),
                'restore' => $category->trashed() ? $categories->restore($category) : null,
                'forceDelete' => $categories->forceDelete($category),
            };

            $done++;
        }

        $this->selected = [];
        $this->bulkAction = '';
        $this->notice = "{$done} categor(y/ies) updated.";
    }

    #[Computed]
    public function stats(): array
    {
        return app(CategoryService::class)->stats();
    }

    #[Computed]
    public function categories()
    {
        return app(CategoryService::class)->searchPaginated($this->search, 10, [
            'status' => in_array($this->status, ['active', 'inactive'], true) ? $this->status : null,
            'trashed' => $this->status === 'trashed' ? 'only' : null,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ]);
    }

    public function render()
    {
        return $this->view();
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2>{{ __('categories.categories') }}</h2>
            <p class="text-muted ms-note">{{ __('categories.organize_your_posts_into_categories') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            @foreach (config('spreadsheets.export_formats') as $format)
                <a href="{{ route('admin.categories.export', $format) }}" class="btn">{{ __('imports.export', ['format' => strtoupper($format)]) }}</a>
            @endforeach
            @can('create', \App\Models\Category::class)
                <a href="{{ route('admin.categories.import') }}" wire:navigate class="btn">{{ __('imports.import') }}</a>
            @endcan
            <a href="{{ route('admin.categories.create') }}" wire:navigate class="btn btn-primary">
                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M12 5v14M5 12h14"></path></svg>
                {{ __('categories.new_category') }}
            </a>
        </div>
    </div>

    @if (session('status'))
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    <div class="stat-cards">
        @foreach ([
            ['categories.total_categories', $this->stats['total']],
            ['categories.active', $this->stats['active']],
            ['categories.inactive', $this->stats['inactive']],
            ['Trashed', $this->stats['trashed']],
        ] as [$label, $value])
            <div class="card">
                <div class="card-kicker">{{ __($label) }}</div>
                <div class="stat-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    <div class="ms-toolbar">
        <div class="ms-search-field">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="rgba(var(--ink),.5)" stroke-width="1.5" class="ms-input-icon"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4-4"></path></svg>
            <input wire:model.live.debounce.300ms="search" class="input ms-pl-32" placeholder="{{ __('categories.search_placeholder') }}">
        </div>

        <div class="ms-row-10">
            <select wire:model.live="status" class="input" aria-label="Status">
                <option value="all">{{ __('categories.all_statuses') }}</option>
                <option value="active">{{ __('categories.active') }}</option>
                <option value="inactive">{{ __('categories.inactive') }}</option>
                <option value="trashed">Trashed</option>
            </select>
        </div>
    </div>

    @if (count($selected) > 0)
        <div class="ms-toolbar">
            <div class="ms-row-10">
                <span class="text-muted ms-fs-13">{{ count($selected) }} selected</span>
                <select wire:model="bulkAction" class="input" aria-label="Bulk action">
                    <option value="">Choose action…</option>
                    @if ($status === 'trashed')
                        <option value="restore">Restore</option>
                        <option value="forceDelete">Delete permanently</option>
                    @else
                        <option value="activate">Activate</option>
                        <option value="deactivate">Deactivate</option>
                        <option value="delete">Move to trash</option>
                    @endif
                </select>
                <button
                    type="button"
                    wire:click="applyBulk"
                    wire:confirm="Apply this action to the selected categories?"
                    wire:loading.attr="disabled"
                    class="btn"
                    @disabled($bulkAction === '')
                >Apply</button>
            </div>
            <button type="button" wire:click="$set('selected', [])" class="btn ms-btn-sm">Clear selection</button>
        </div>
    @endif

    @if ($notice)
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ $notice }}</div>
    @endif

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                <div class="row-cards-toolbar">
                    <div class="row-cards-control"><input type="checkbox" class="checkbox" aria-label="Select all on this page" wire:click="selectPage"></div>
                    <div class="row-cards-control"><button type="button" wire:click="sortBy('name')" class="ms-sort-btn">{{ __('categories.name') }}</button></div>
                </div>
                    @forelse ($this->categories as $category)
                        <div class="row-card" wire:key="category-{{ $category->id }}">
                            <div class="row-card-select">
                                <input type="checkbox" class="checkbox" value="{{ $category->id }}" wire:model.live="selected" aria-label="Select category">
                            </div>
                            <div class="row-card-title">
                                <div class="ms-fw-500">{{ $category->name }}</div>
                                @if ($category->parent)
                                    <div class="text-muted ms-fs-12">{{ __('categories.subcategory_of', ['name' => $category->parent->name]) }}</div>
                                @elseif ($category->children_count > 0)
                                    <div class="text-muted ms-fs-12">{{ __('categories.subcategories') }}: {{ $category->children_count }}</div>
                                @endif
                            </div>
                            <div class="row-card-field text-muted" data-label="{{ __('categories.slug') }}">{{ $category->slug }}</div>
                            <div class="row-card-field text-muted" data-label="{{ __('categories.posts') }}">{{ $category->posts_count }}</div>
                            <div class="row-card-field" data-label="{{ __('categories.status') }}">
                                @if ($category->trashed())
                                    <span class="tag tag-outline">Trashed</span>
                                @else
                                    <span class="tag {{ $category->is_active ? 'tag-accent' : 'tag-neutral' }}">{{ $category->is_active ? 'Active' : 'Inactive' }}</span>
                                @endif
                            </div>
                            <div class="row-card-actions">
                                <div class="ms-actions-end">
                                    @if ($category->trashed())
                                        @can('restore', $category)
                                            <button type="button" wire:click="restoreCategory({{ $category->id }})" class="btn ms-btn-sm">Restore</button>
                                        @endcan
                                        @can('forceDelete', $category)
                                            <button
                                                type="button"
                                                wire:click="forceDeleteCategory({{ $category->id }})"
                                                wire:confirm="Permanently delete this category? This cannot be undone."
                                                class="btn ms-btn-sm-danger"
                                            >Delete permanently</button>
                                        @endcan
                                    @else
                                        @can('update', $category)
                                            <a href="{{ route('admin.categories.edit', $category) }}" wire:navigate class="btn ms-btn-sm">{{ __('categories.edit') }}</a>
                                        @endcan
                                        @can('delete', $category)
                                            <button
                                                type="button"
                                                wire:click="deleteCategory({{ $category->id }})"
                                                wire:confirm="Move this category to the trash?"
                                                class="btn ms-btn-sm-danger"
                                            >{{ __('categories.delete') }}</button>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('categories.no_categories_found') }}</div>
                    @endforelse
            </div>
        </div>

        <div class="ms-cell-top">
            {{ $this->categories->links() }}
        </div>
    </div>

</div>

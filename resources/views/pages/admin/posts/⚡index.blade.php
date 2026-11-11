<?php

use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PdfService;
use App\Services\PostService;
use App\Services\SettingService;
use App\Services\SpreadsheetService;
use App\Services\TagService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app')] #[Title('Posts')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** Ability each bulk action requires on every selected post. */
    private const BULK_ABILITIES = [
        'publish' => 'update',
        'draft' => 'update',
        'delete' => 'delete',
        'restore' => 'restore',
        'forceDelete' => 'forceDelete',
    ];

    #[Url(as: 'q', history: true)]
    public string $search = '';

    /** all | published | draft | trashed */
    #[Url(as: 'status', history: true)]
    public string $status = 'all';

    #[Url(as: 'category', history: true)]
    public string $category = '';

    #[Url(as: 'tag', history: true)]
    public string $tag = '';

    /** title | published_at | created_at */
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
        $this->authorize('viewAny', Post::class);
    }

    #[Computed]
    public function dateFormat(): string
    {
        return app(SettingService::class)->dateFormat();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'category', 'tag', 'sort', 'direction'], true)) {
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
        $this->selected = $this->posts->getCollection()->pluck('id')->map(fn ($id) => (string) $id)->all();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedCategory(): void
    {
        $this->resetPage();
    }

    /**
     * Spreadsheet download honouring the current filters (q, status, category, tag).
     */
    public function export(string $format, PostService $posts, SpreadsheetService $spreadsheets): StreamedResponse
    {
        $this->authorize('viewAny', Post::class);

        $rows = $posts->exportRows(trim($this->search) ?: null, [
            'status' => in_array($this->status, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true) ? $this->status : null,
            'trashed' => $this->status === 'trashed' ? 'only' : null,
            'category_id' => ctype_digit($this->category) ? (int) $this->category : null,
            'tag' => ctype_digit($this->tag) ? (int) $this->tag : null,
        ]);

        return $spreadsheets->download($rows, 'posts', $format);
    }

    /**
     * The post as a PDF, generated on the fly and never stored.
     */
    public function downloadPdf(int $postId, PdfService $pdf): StreamedResponse
    {
        $post = app(\App\Services\PostService::class)->findOrFail($postId);
        $this->authorize('view', $post);

        $post->loadMissing(['author', 'category', 'tags']);

        return $pdf->download($pdf->document('pdf.post', ['post' => $post], 'post-'.$post->slug));
    }

    public function deletePost(int $postId, PostService $posts): void
    {
        $post = app(\App\Services\PostService::class)->findOrFail($postId);
        $this->authorize('delete', $post);

        $posts->delete($post);

        $this->toast('Post moved to the trash.');
    }

    public function restorePost(int $postId, PostService $posts): void
    {
        $post = app(\App\Services\PostService::class)->onlyTrashed()->findOrFail($postId);
        $this->authorize('restore', $post);

        $posts->restore($post);

        $this->toast('Post restored.');
    }

    public function forceDeletePost(int $postId, PostService $posts): void
    {
        $post = app(\App\Services\PostService::class)->onlyTrashed()->findOrFail($postId);
        $this->authorize('forceDelete', $post);

        $posts->forceDelete($post);

        $this->toast('Post permanently deleted.');
    }

    public function applyBulk(PostService $posts): void
    {
        $action = $this->bulkAction;

        if (! array_key_exists($action, self::BULK_ABILITIES) || $this->selected === []) {
            return;
        }

        $ability = self::BULK_ABILITIES[$action];
        $user = auth()->user();
        $done = 0;

        foreach (app(\App\Services\PostService::class)->withTrashed()->whereIn('id', $this->selected)->get() as $post) {
            if ($user->cannot($ability, $post)) {
                continue;
            }

            // Trashed posts can only be restored or removed for good, never edited in bulk.
            if ($post->trashed() && in_array($action, ['publish', 'draft', 'delete'], true)) {
                continue;
            }

            match ($action) {
                'publish' => $posts->setStatus($post, Post::STATUS_PUBLISHED),
                'draft' => $posts->setStatus($post, Post::STATUS_DRAFT),
                'delete' => $posts->delete($post),
                'restore' => $post->trashed() ? $posts->restore($post) : null,
                'forceDelete' => $posts->forceDelete($post),
            };

            $done++;
        }

        $this->selected = [];
        $this->bulkAction = '';
        $this->notice = "{$done} post(s) updated.";
    }

    #[Computed]
    public function stats(): array
    {
        return app(PostService::class)->stats();
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->options();
    }

    #[Computed]
    public function tagOptions(): array
    {
        return app(TagService::class)->options();
    }

    /**
     * The current filters, shared by the listing and the export links.
     *
     * @return array<string, mixed>
     */
    public function filters(): array
    {
        return [
            'status' => in_array($this->status, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true) ? $this->status : null,
            'trashed' => $this->status === 'trashed' ? 'only' : null,
            'category_id' => $this->category !== '' ? (int) $this->category : null,
            'tag' => $this->tag !== '' ? (int) $this->tag : null,
            'sort' => $this->sort,
            'direction' => $this->direction,
        ];
    }

    #[Computed]
    public function posts()
    {
        return app(PostService::class)->searchPaginated($this->search, 10, $this->filters());
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
            <h2>{{ __('posts.list.title') }}</h2>
            <p class="text-muted ms-note">{{ __('posts.write_and_publish_content') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            @foreach (config('spreadsheets.export_formats') as $format)
                <button
                    type="button"
                    wire:click="export('{{ $format }}')"
                    class="btn"
                    title="{{ __('imports.export_filtered') }}"
                >{{ __('imports.export', ['format' => strtoupper($format)]) }}</button>
            @endforeach
        <a href="{{ route('admin.posts.create') }}" wire:navigate class="btn btn-primary">
            <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M12 5v14M5 12h14"></path></svg>
            {{ __('posts.new_post') }}
        </a>
        </div>
    </div>

    @if (session('status'))
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    <div class="stat-cards">
        @foreach ([
            ['posts.total_posts', $this->stats['total']],
            ['posts.published', $this->stats['published']],
            ['posts.drafts', $this->stats['drafts']],
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
            <input wire:model.live.debounce.300ms="search" class="input ms-pl-32" placeholder="{{ __('posts.search_placeholder') }}">
        </div>

        <div class="ms-row-10">
            <select wire:model.live="status" class="input" aria-label="{{ __('actions.status') }}">
                <option value="all">{{ __('posts.all_statuses') }}</option>
                <option value="published">{{ __('actions.published') }}</option>
                <option value="draft">{{ __('actions.drafts') }}</option>
                <option value="trashed">{{ __('actions.trashed') }}</option>
            </select>
            <select wire:model.live="category" class="input" aria-label="{{ __('actions.category') }}">
                <option value="">{{ __('posts.all_categories') }}</option>
                @foreach ($this->categories as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            @if ($this->tagOptions !== [])
                <select wire:model.live="tag" class="input" aria-label="{{ __('tags.label') }}">
                    <option value="">{{ __('tags.all_tags') }}</option>
                    @foreach ($this->tagOptions as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
            @endif
        </div>
    </div>

    @if (count($selected) > 0)
        <div class="ms-toolbar">
            <div class="ms-row-10">
                <span class="text-muted ms-fs-13">{{ count($selected) }} selected</span>
                <select wire:model="bulkAction" class="input" aria-label="{{ __('actions.bulk_action') }}">
                    <option value="">{{ __('actions.choose_action') }}</option>
                    @if ($status === 'trashed')
                        <option value="restore">{{ __('actions.restore') }}</option>
                        <option value="forceDelete">{{ __('actions.delete_permanently') }}</option>
                    @else
                        <option value="publish">{{ __('actions.publish') }}</option>
                        <option value="draft">{{ __('actions.move_to_drafts') }}</option>
                        <option value="delete">{{ __('actions.move_to_trash') }}</option>
                    @endif
                </select>
                <button
                    type="button"
                    wire:click="applyBulk"
                    wire:confirm="{{ __('posts.list.confirm_bulk') }}"
                    wire:loading.attr="disabled"
                    class="btn"
                    @disabled($bulkAction === '')
                >{{ __('actions.apply') }}</button>
            </div>
            <button type="button" wire:click="$set('selected', [])" class="btn ms-btn-sm">{{ __('actions.clear_selection') }}</button>
        </div>
    @endif

    @if ($notice)
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ $notice }}</div>
    @endif

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                <div class="row-cards-toolbar">
                    <div class="row-cards-control"><input type="checkbox" class="checkbox" aria-label="{{ __('actions.select_page') }}" wire:click="selectPage"></div>
                    <div class="row-cards-control"><button type="button" wire:click="sortBy('title')" class="ms-sort-btn">{{ __('posts.title') }}</button></div>
                    <div class="row-cards-control"><button type="button" wire:click="sortBy('published_at')" class="ms-sort-btn">{{ __('actions.published') }}</button></div>
                </div>
                    @forelse ($this->posts as $post)
                        <div class="row-card" wire:key="post-{{ $post->id }}">
                            <div class="row-card-select">
                                <input type="checkbox" class="checkbox" value="{{ $post->id }}" wire:model.live="selected" aria-label="{{ __('posts.list.select') }}">
                            </div>
                            <div class="row-card-title">
                                <div class="ms-fw-500">{{ $post->title }}</div>
                                <div class="text-muted ms-fs-12">{{ $post->slug }}</div>
                            </div>
                            <div class="row-card-field text-muted" data-label="{{ __('posts.category') }}">{{ $post->category?->name ?? '—' }}</div>
                            <div class="row-card-field text-muted" data-label="{{ __('posts.author') }}">{{ $post->author?->name ?? '—' }}</div>
                            <div class="row-card-field text-muted ms-nowrap" data-label="Published">{{ $post->published_at?->format($this->dateFormat) ?? '—' }}</div>
                            <div class="row-card-field" data-label="{{ __('posts.status') }}">
                                @if ($post->trashed())
                                    <span class="tag tag-outline">{{ __('actions.trashed') }}</span>
                                @else
                                    <span class="tag {{ $post->isPublished() ? 'tag-accent' : 'tag-neutral' }}">{{ \App\Models\Post::statuses()[$post->status] ?? $post->status }}</span>
                                @endif
                            </div>
                            <div class="row-card-actions">
                                <div class="ms-actions-end">
                                    @if ($post->trashed())
                                        @can('restore', $post)
                                            <button type="button" wire:click="restorePost({{ $post->id }})" class="btn ms-btn-sm">{{ __('actions.restore') }}</button>
                                        @endcan
                                        @can('forceDelete', $post)
                                            <button
                                                type="button"
                                                wire:click="forceDeletePost({{ $post->id }})"
                                                wire:confirm="{{ __('posts.list.confirm_force_delete') }}"
                                                class="btn ms-btn-sm-danger"
                                            >{{ __('actions.delete_permanently') }}</button>
                                        @endcan
                                    @else
                                        @can('update', $post)
                                            <a href="{{ route('admin.posts.edit', $post) }}" wire:navigate class="btn ms-btn-sm">{{ __('actions.edit') }}</a>
                                        @endcan
                                        @can('view', $post)
                                            <button type="button" wire:click="downloadPdf({{ $post->id }})" class="btn ms-btn-sm" title="{{ __('pdf.download') }}">PDF</button>
                                        @endcan
                                        @can('delete', $post)
                                            <button
                                                type="button"
                                                wire:click="deletePost({{ $post->id }})"
                                                wire:confirm="{{ __('posts.list.confirm_trash') }}"
                                                class="btn ms-btn-sm-danger"
                                            >{{ __('posts.delete') }}</button>
                                        @endcan
                                    @endif
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('posts.no_posts_found') }}</div>
                    @endforelse
            </div>
        </div>

        <div class="ms-cell-top">
            {{ $this->posts->links() }}
        </div>
    </div>

</div>

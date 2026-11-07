<?php

use App\Domain\Questions\Enums\Difficulty;
use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\Imports\QuestionExportService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionQueries;
use App\Domain\Questions\QuestionService;
use App\Services\CategoryService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts::app')] #[Title('Questions')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(as: 'category', history: true)]
    public string $categoryId = '';

    #[Url(history: true)]
    public string $difficulty = '';

    #[Url(history: true)]
    public string $language = '';

    #[Url(history: true)]
    public string $source = '';

    public function export(string $format, QuestionExportService $exports): StreamedResponse
    {
        $this->authorize('manage questions');

        return $exports->download($format);
    }

    public function mount(): void
    {
        $this->authorize('manage questions');
    }

    public function updated(string $property): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function questions()
    {
        return app(QuestionQueries::class)->search([
            'search' => $this->search,
            'status' => in_array($this->status, QuestionStatus::values(), true) ? $this->status : null,
            'category_id' => $this->categoryId ?: null,
            'difficulty' => in_array($this->difficulty, Difficulty::values(), true) ? $this->difficulty : null,
            'language' => array_key_exists($this->language, config('app.available_locales')) ? $this->language : null,
            'source' => in_array($this->source, QuestionSource::values(), true) ? $this->source : null,
        ]);
    }

    #[Computed]
    public function counts(): array
    {
        return app(QuestionQueries::class)->statusCounts();
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->parentOptions();
    }

    public function deleteQuestion(string $uuid, QuestionService $questions): void
    {
        $this->authorize('manage questions');
        $questions->delete(app(\App\Domain\Questions\QuestionService::class)->query()->where('uuid', $uuid)->firstOrFail(), auth()->user());
        $this->toast(__('questions.admin.deleted'));
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('questions.admin.title') }}</h2>
            <p class="text-muted ms-note">{{ __('questions.admin.description') }}</p>
        </div>
        <div class="ms-row-10 ms-wrap">
            @foreach (['csv', 'xlsx', 'json'] as $format)
                <button type="button" wire:click="export('{{ $format }}')" class="btn">{{ __('questions.admin.export', ['format' => strtoupper($format)]) }}</button>
            @endforeach
            <a href="{{ route('admin.questions.import') }}" wire:navigate class="btn">{{ __('questions.import.title') }}</a>
            <a href="{{ route('admin.questions.create') }}" wire:navigate class="btn btn-primary">{{ __('questions.admin.new') }}</a>
        </div>
    </div>

    <div class="ms-row-10 ms-wrap ms-mb-24">
        @foreach ($this->counts as $value => $count)
            <button type="button" wire:click="$set('status', '{{ $status === $value ? '' : $value }}')" @class(['tag', 'tag-accent' => $status === $value, 'tag-neutral' => $status !== $value])>
                {{ __('questions.status.'.$value) }} · {{ $count }}
            </button>
        @endforeach
    </div>

    <div class="ms-toolbar">
        <div class="ms-search-field">
            <input wire:model.live.debounce.300ms="search" class="input" placeholder="{{ __('questions.admin.search') }}" aria-label="{{ __('questions.admin.search') }}">
        </div>
        <div class="ms-row-10 ms-wrap">
            <select wire:model.live="categoryId" class="input" aria-label="{{ __('questions.fields.category') }}">
                <option value="">{{ __('questions.admin.all_categories') }}</option>
                @foreach ($this->categories as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <select wire:model.live="difficulty" class="input" aria-label="{{ __('questions.fields.difficulty') }}">
                <option value="">{{ __('questions.admin.all_difficulties') }}</option>
                @foreach (\App\Domain\Questions\Enums\Difficulty::cases() as $level)
                    <option value="{{ $level->value }}">{{ __('questions.difficulty.'.$level->value) }}</option>
                @endforeach
            </select>
            <select wire:model.live="language" class="input" aria-label="{{ __('questions.fields.language') }}">
                <option value="">{{ __('questions.admin.all_languages') }}</option>
                @foreach (config('app.available_locales') as $code => $meta)
                    <option value="{{ $code }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <select wire:model.live="source" class="input" aria-label="{{ __('questions.fields.source') }}">
                <option value="">{{ __('questions.admin.all_sources') }}</option>
                @foreach (\App\Domain\Questions\Enums\QuestionSource::cases() as $origin)
                    <option value="{{ $origin->value }}">{{ __('questions.source.'.$origin->value) }}</option>
                @endforeach
            </select>
        </div>
    </div>

    <div class="card ms-p-0">
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->questions as $question)
                        <div class="row-card" wire:key="question-{{ $question->id }}">
                            <div class="row-card-title">
                                <div class="ms-fw-500">{{ \Illuminate\Support\Str::limit($question->translations->firstWhere('language', $question->origin_language)?->body ?? '—', 90) }}</div>
                                <div class="text-muted ms-fs-12">{{ __('questions.source.'.$question->source->value) }}@if ($question->author) · {{ $question->author->name }}@endif · v{{ $question->version }}</div>
                            </div>
                            <div class="row-card-field text-muted" data-label="{{ __('questions.fields.category') }}">{{ $question->category?->name }}</div>
                            <div class="row-card-field text-muted" data-label="{{ __('questions.fields.difficulty') }}">{{ __('questions.difficulty.'.$question->difficulty->value) }}</div>
                            <div class="row-card-field" data-label="{{ __('questions.fields.languages') }}">
                                @foreach ($question->translations->sortBy('language') as $translation)
                                    <span class="tag {{ $translation->status->value === 'approved' ? 'tag-accent' : 'tag-neutral' }}" title="{{ __('questions.translation_status.'.$translation->status->value) }}">{{ strtoupper($translation->language) }}</span>
                                @endforeach
                            </div>
                            <div class="row-card-field" data-label="{{ __('questions.fields.status') }}"><x-questions.status-tag :status="$question->status" /></div>
                            <div class="row-card-actions">
                                <div class="ms-actions-end">
                                    <a href="{{ route('admin.questions.edit', $question) }}" wire:navigate class="btn ms-btn-sm">{{ __('questions.admin.edit') }}</a>
                                    <button type="button" wire:click="deleteQuestion('{{ $question->uuid }}')" wire:confirm="{{ __('questions.admin.delete_confirm') }}" class="btn ms-btn-sm-danger">{{ __('questions.admin.delete') }}</button>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('questions.admin.empty') }}</div>
                    @endforelse
            </div>
        </div>
        <div class="ms-cell-top">{{ $this->questions->links() }}</div>
    </div>
</div>

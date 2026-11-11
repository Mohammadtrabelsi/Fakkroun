<?php

use App\Domain\Questions\Imports\QuestionExportService;
use App\Domain\Questions\Imports\QuestionImportService;
use App\Domain\Questions\Models\QuestionImportBatch;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Question import (spec §11): upload → map columns → preview → start → history.
 * Processing runs on the queue (ProcessQuestionImport); imports never publish.
 */
new #[Layout('layouts::app')] #[Title('Import questions')] class extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    /** @var \Illuminate\Http\UploadedFile|null */
    public $file = null;

    /** @var array<int, string> */
    public array $headers = [];

    /** @var array<string, string> field => header */
    public array $mapping = [];

    public string $language = 'en';

    /** @var array<int, array<string, mixed>> */
    public array $preview = [];

    public ?int $openBatch = null;

    public function export(string $format, QuestionExportService $exports): StreamedResponse
    {
        $this->authorize('manage questions');

        return $exports->download($format);
    }

    public function mount(): void
    {
        $this->authorize('manage questions');
        $this->language = app()->getLocale();
    }

    public function updatedFile(): void
    {
        $imports = app(QuestionImportService::class);
        $this->validate(['file' => ['required', 'file', 'max:'.(int) config('fakkroun.questions.import_max_kb'), 'mimes:csv,txt,xlsx,json']]);
        $this->preview = [];

        try {
            $format = $imports->formatOf($this->file->getClientOriginalName());
            $this->headers = $imports->headers($this->file->getRealPath(), $format);
            $this->mapping = $imports->suggestMapping($this->headers);
        } catch (\DomainException $e) {
            $this->addError('file', $e->getMessage());
        }
    }

    #[Computed]
    public function fields(): array
    {
        return app(QuestionImportService::class)->fields();
    }

    #[Computed]
    public function required(): array
    {
        return app(QuestionImportService::class)->requiredFields();
    }

    #[Computed]
    public function batches()
    {
        return app(\App\Domain\Questions\QuestionImportBatchService::class)->query()->with('user:id,name')->latest('id')->paginate(10);
    }

    /** First rejected rows of the batch whose details are open. */
    #[Computed]
    public function openBatchErrors()
    {
        $batch = $this->openBatch ? $this->batches->firstWhere('id', $this->openBatch) : null;

        return $batch ? $batch->errors()->limit(50)->get() : collect();
    }

    public function runPreview(QuestionImportService $imports): void
    {
        $this->authorize('manage questions');

        if (! $this->file) {
            return;
        }

        try {
            $this->preview = $imports->preview($this->file->getRealPath(), $imports->formatOf($this->file->getClientOriginalName()), array_filter($this->mapping), ['language' => $this->language]);
        } catch (ValidationException $e) {
            $this->addError('mapping', collect($e->errors())->flatten()->first());
        }
    }

    public function start(QuestionImportService $imports): void
    {
        $this->authorize('manage questions');

        if (! $this->file) {
            return;
        }

        try {
            $batch = $imports->start(auth()->user(), $this->file, array_filter($this->mapping), ['language' => $this->language]);
        } catch (ValidationException $e) {
            $this->addError('mapping', collect($e->errors())->flatten()->first());

            return;
        } catch (\DomainException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }

        $this->reset(['file', 'headers', 'mapping', 'preview']);
        $this->openBatch = $batch->id;
        $this->toast(__('questions.import.started'));
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('questions.import.title') }}</h2>
            <p class="text-muted ms-note">{{ __('questions.import.description') }}</p>
        </div>
        <button type="button" wire:click="export('csv')" class="btn">{{ __('questions.import.template') }}</button>
    </div>

    <div class="card ms-stack-16">
        <div class="field ms-mb-0">
            <x-input-label for="file" :value="__('questions.import.file')" />
            <input type="file" wire:model="file" id="file" accept=".csv,.xlsx,.json" class="input">
            <div wire:loading wire:target="file" class="text-muted ms-fs-12">Uploading…</div>
            <x-input-error :message="$errors->first('file')" />
        </div>

        @if ($headers !== [])
            <div class="field ms-mb-0">
                <x-input-label for="language" :value="__('questions.import.default_language')" />
                <select wire:model="language" id="language" class="input">
                    @foreach (config('app.available_locales') as $code => $meta)<option value="{{ $code }}">{{ $meta['label'] }}</option>@endforeach
                </select>
            </div>

            <h3 class="ms-fs-18">{{ __('questions.import.mapping') }}</h3>
            <div class="grid gap-3 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                @foreach ($this->fields as $field)
                    <div class="field ms-mb-0" wire:key="map-{{ $field }}">
                        <x-input-label :for="'map-'.$field" :value="$field.(in_array($field, $this->required, true) ? ' *' : '')" />
                        <select wire:model="mapping.{{ $field }}" id="map-{{ $field }}" class="input">
                            <option value="">—</option>
                            @foreach ($headers as $header)<option value="{{ $header }}">{{ $header }}</option>@endforeach
                        </select>
                    </div>
                @endforeach
            </div>
            <x-input-error :message="$errors->first('mapping')" />

            <div class="ms-row-10">
                <button type="button" wire:click="runPreview" class="btn">{{ __('questions.import.preview') }}</button>
                <button type="button" wire:click="start" class="btn btn-primary">{{ __('questions.import.start') }}</button>
            </div>

            @if ($preview !== [])
                <div class="row-cards-wrap">
                    <div class="row-cards">
                            @foreach ($preview as $row)
                                <div class="row-card">
                                    <div class="row-card-kicker">#{{ $row['row'] }}</div>
                                    <div class="row-card-field" data-label="{{ __('questions.fields.body') }}">{{ $row['question'] }}</div>
                                    <div class="row-card-field ms-fs-12" data-label="{{ __('questions.import.checks') }}">
                                        @if ($row['errors'] !== [])<span class="tag tag-outline">{{ implode(' · ', $row['errors']) }}</span>
                                        @elseif ($row['duplicate'])<span class="tag tag-neutral">{{ __('questions.import.duplicate') }}</span>
                                        @else<span class="tag tag-accent">{{ __('questions.import.ok') }}</span>@endif
                                        @foreach ($row['warnings'] as $warning)<div class="text-muted">{{ $warning }}</div>@endforeach
                                    </div>
                                </div>
                            @endforeach
                    </div>
                </div>
            @endif
        @endif
    </div>

    <div class="card ms-p-0 ms-mt-18" wire:poll.5s>
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @forelse ($this->batches as $batch)
                        <div class="row-card" wire:key="ib-{{ $batch->id }}">
                            <div class="row-card-title">
                                <button type="button" wire:click="$set('openBatch', {{ $openBatch === $batch->id ? 'null' : $batch->id }})" class="ms-fw-500 ms-linkbtn">{{ $batch->original_name }}</button>
                                <div class="text-muted ms-fs-12">{{ $batch->user?->name }} · {{ $batch->created_at->diffForHumans() }}</div>
                                @if ($batch->error)<div class="text-muted ms-fs-12">{{ $batch->error }}</div>@endif
                                @if ($openBatch === $batch->id)
                                    <ul class="ms-fs-12">
                                        @foreach ($this->openBatchErrors as $error)
                                            <li>{{ __('questions.import.row', ['row' => $error->row_number]) }}: {{ implode(' · ', $error->messages) }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="row-card-field" data-label="{{ __('questions.fields.status') }}"><span class="tag {{ $batch->status === 'completed' ? 'tag-accent' : 'tag-neutral' }}">{{ __('questions.import.status.'.$batch->status) }}</span></div>
                            <div class="row-card-field text-muted ms-fs-12" data-label="{{ __('questions.import.counts_header') }}">{{ __('questions.import.counts', ['created' => $batch->created_count, 'duplicates' => $batch->duplicate_count, 'failed' => $batch->failed_count, 'skipped' => $batch->skipped_count]) }}</div>
                        </div>
                    @empty
                        <div class="row-card row-card-empty text-muted ms-empty">{{ __('questions.import.empty') }}</div>
                    @endforelse
            </div>
        </div>
        <div class="ms-cell-top">{{ $this->batches->links() }}</div>
    </div>
</div>

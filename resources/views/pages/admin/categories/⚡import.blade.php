<?php

use App\Models\Category;
use App\Services\CategoryService;
use App\Services\SpreadsheetService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('Import categories')] class extends Component
{
    use WithFileUploads;

    public $file = null;

    /** @var array<string, mixed>|null */
    public ?array $result = null;

    public function mount(): void
    {
        $this->authorize('create', Category::class);
    }

    public function import(SpreadsheetService $spreadsheets, CategoryService $categories): void
    {
        $this->authorize('create', Category::class);

        $formats = (array) config('spreadsheets.import_formats', ['csv', 'xlsx']);

        $this->validate([
            // Extension and detected MIME type must both match; CSV is
            // often reported as text/plain.
            'file' => [
                'required',
                'file',
                'max:'.(int) config('spreadsheets.max_import_kb', 5120),
                'extensions:'.implode(',', $formats),
                'mimes:'.implode(',', array_merge($formats, in_array('csv', $formats, true) ? ['txt'] : [])),
            ],
        ]);

        $format = strtolower($this->file->getClientOriginalExtension());

        try {
            $this->result = $spreadsheets->import(
                $this->file->getRealPath(),
                $format,
                $categories->importRules(),
                fn (array $row): bool => $categories->importRow($row),
                $categories->importRequiredHeaders(),
            )->toArray();
        } catch (\Throwable $e) {
            report($e);
            $this->addError('file', __('imports.unreadable'));
        } finally {
            // Never keep the uploaded file around.
            $this->file?->delete();
            $this->reset('file');
        }
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <a href="{{ route('admin.categories.index') }}" wire:navigate class="text-muted ms-fs-13">&larr; {{ __('imports.back_to_categories') }}</a>
        <h2 class="ms-mt-8">{{ __('imports.categories_title') }}</h2>
        <p class="text-muted ms-note">{{ __('imports.categories_help') }}</p>
    </div>

    <form wire:submit="import" class="card ms-stack-16 ms-mb-24">
        <div class="field ms-mb-0">
            <x-input-label for="file" :value="__('imports.file')" />
            <input type="file" wire:model="file" id="file" class="input" accept=".{{ implode(',.', config('spreadsheets.import_formats')) }}">
            <div wire:loading wire:target="file" class="text-muted ms-fs-12">{{ __('imports.uploading') }}</div>
            <x-input-error :message="$errors->first('file')" />
            <div class="text-muted ms-fs-12">
                {{ __('imports.limits', ['formats' => strtoupper(implode(', ', config('spreadsheets.import_formats'))), 'kb' => config('spreadsheets.max_import_kb'), 'rows' => config('spreadsheets.max_import_rows')]) }}
            </div>
            <div class="text-muted ms-fs-12">{{ __('imports.categories_columns') }}</div>
        </div>

        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="import,file">
                <span wire:loading.remove wire:target="import">{{ __('imports.start') }}</span>
                <span wire:loading wire:target="import">{{ __('imports.importing') }}</span>
            </button>
            <a href="{{ route('admin.categories.export', 'csv') }}" class="btn">{{ __('imports.download_template') }}</a>
        </div>
    </form>

    @if ($result)
        <x-admin.import-result :result="$result" />
    @endif
</div>

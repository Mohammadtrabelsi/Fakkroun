<?php

use App\Domain\Ai\GenerationService;
use App\Domain\Ai\Models\AiGenerationBatch;
use App\Domain\Ai\QuestionDrafter;
use App\Services\CategoryService;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * AI-assisted question generation (spec §10). Batches run on the queue; every
 * generated question lands as an AI draft for moderators.
 */
new #[Layout('layouts::app')] #[Title('AI generation')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    public string $categoryId = '';

    public string $difficulty = 'medium';

    public string $language = 'en';

    public int $count = 10;

    public string $audience = '';

    public string $topic = '';

    public bool $requireExplanation = true;

    public bool $requireReference = true;

    public function mount(): void
    {
        $this->authorize('manage questions');
        $this->language = app()->getLocale();
    }

    #[Computed]
    public function enabled(): bool
    {
        return app(QuestionDrafter::class)->enabled();
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->parentOptions();
    }

    #[Computed]
    public function batches()
    {
        return AiGenerationBatch::query()->with('user:id,name')->latest('id')->paginate(10);
    }

    public function start(GenerationService $generation): void
    {
        $this->authorize('manage questions');

        try {
            $generation->start(auth()->user(), [
                'category_id' => $this->categoryId !== '' ? (int) $this->categoryId : null,
                'difficulty' => $this->difficulty,
                'language' => $this->language,
                'count' => $this->count,
                'audience' => $this->audience ?: null,
                'topic' => $this->topic ?: null,
                'require_explanation' => $this->requireExplanation,
                'require_reference' => $this->requireReference,
            ]);
        } catch (ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError($key, $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->toast($e->getMessage(), 'error');

            return;
        }

        $this->toast(__('ai.started'));
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('ai.title') }}</h2>
        <p class="text-muted ms-note">{{ __('ai.description') }}</p>
    </div>

    @unless ($this->enabled)
        <div class="tag tag-outline ms-notice">{{ __('ai.disabled_notice') }}</div>
    @endunless

    <form wire:submit="start" class="card ms-stack-16">
        <div class="ms-row-10 ms-wrap">
            <div class="field ms-mb-0">
                <x-input-label for="categoryId" :value="__('questions.fields.category')" />
                <select wire:model="categoryId" id="categoryId" class="input">
                    <option value="">{{ __('questions.fields.choose') }}</option>
                    @foreach ($this->categories as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                </select>
                <x-input-error :message="$errors->first('category_id')" />
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="difficulty" :value="__('questions.fields.difficulty')" />
                <select wire:model="difficulty" id="difficulty" class="input">
                    @foreach (['easy', 'medium', 'hard'] as $level)<option value="{{ $level }}">{{ __('questions.difficulty.'.$level) }}</option>@endforeach
                </select>
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="language" :value="__('questions.fields.language')" />
                <select wire:model="language" id="language" class="input">
                    @foreach (config('app.available_locales') as $code => $meta)<option value="{{ $code }}">{{ $meta['label'] }}</option>@endforeach
                </select>
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="count" :value="__('ai.count')" />
                <x-text-input wire:model="count" id="count" type="number" min="1" :max="config('fakkroun.ai.batch_max_questions')" :error="$errors->first('count')" />
                <x-input-error :message="$errors->first('count')" />
            </div>
        </div>
        <div class="field ms-mb-0">
            <x-input-label for="topic" :value="__('ai.topic')" />
            <x-text-input wire:model="topic" id="topic" type="text" />
        </div>
        <div class="field ms-mb-0">
            <x-input-label for="audience" :value="__('ai.audience')" />
            <x-text-input wire:model="audience" id="audience" type="text" :placeholder="__('ai.audience_placeholder')" />
        </div>
        <label class="ms-check-row"><input type="checkbox" class="checkbox" wire:model="requireExplanation"> {{ __('ai.require_explanation') }}</label>
        <label class="ms-check-row"><input type="checkbox" class="checkbox" wire:model="requireReference"> {{ __('ai.require_reference') }}</label>
        <p class="text-muted ms-fs-12">{{ __('ai.review_notice', ['ceiling' => config('fakkroun.ai.batch_cost_ceiling')]) }}</p>
        <div><button type="submit" class="btn btn-primary" @disabled(! $this->enabled)>{{ __('ai.start') }}</button></div>
    </form>

    <div class="card ms-p-0 ms-mt-18" wire:poll.5s>
        <div class="table-wrap">
            <table class="table">
                <thead><tr><th>{{ __('ai.batch') }}</th><th>{{ __('questions.fields.status') }}</th><th>{{ __('ai.results') }}</th><th>{{ __('ai.cost') }}</th></tr></thead>
                <tbody>
                    @forelse ($this->batches as $batch)
                        <tr wire:key="b-{{ $batch->id }}">
                            <td>
                                <div class="ms-fw-500">#{{ $batch->id }} · {{ __('ai.kind.'.$batch->kind) }} · {{ strtoupper($batch->parameters['language'] ?? '') }}</div>
                                <div class="text-muted ms-fs-12">{{ $batch->model }} · {{ $batch->user?->name }} · {{ $batch->created_at->diffForHumans() }}</div>
                                @if ($batch->error)<div class="text-muted ms-fs-12">{{ $batch->error }}</div>@endif
                            </td>
                            <td><span class="tag {{ $batch->status === 'completed' ? 'tag-accent' : 'tag-neutral' }}">{{ __('ai.status.'.$batch->status) }}</span></td>
                            <td class="text-muted ms-fs-12">{{ __('ai.counts', ['accepted' => $batch->accepted_count, 'duplicates' => $batch->duplicate_count, 'rejected' => $batch->rejected_count, 'requested' => $batch->requested_count]) }}</td>
                            <td class="text-muted ms-nowrap">${{ number_format($batch->estimated_cost, 4) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted ms-empty">{{ __('ai.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ms-cell-top">{{ $this->batches->links() }}</div>
    </div>
</div>

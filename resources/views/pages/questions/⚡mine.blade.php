<?php

use App\Domain\Questions\Models\Question;
use App\Domain\Questions\SubmissionService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('My questions')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    public function mount(): void
    {
        $this->authorize('submit questions');
    }

    #[Computed]
    public function submissions()
    {
        return app(SubmissionService::class)->mine(auth()->user());
    }

    public function withdraw(string $uuid, SubmissionService $submissions): void
    {
        $question = Question::query()->where('uuid', $uuid)->firstOrFail();

        try {
            $submissions->withdraw(auth()->user(), $question);
            $this->toast(__('questions.mine.withdrawn'));
        } catch (\DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        }
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2 class="ms-mb-0">{{ __('questions.mine.title') }}</h2>
            <p class="text-muted ms-note">{{ __('questions.mine.description') }}</p>
        </div>
        <a href="{{ route('questions.submit') }}" wire:navigate class="btn btn-primary">{{ __('questions.submit.title') }}</a>
    </div>

    @if (session('status'))
        <div data-autohide="4000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    <div class="card ms-p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('questions.fields.body') }}</th>
                        <th>{{ __('questions.fields.category') }}</th>
                        <th>{{ __('questions.fields.status') }}</th>
                        <th class="ms-text-right">{{ __('categories.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->submissions as $question)
                        <tr wire:key="q-{{ $question->id }}">
                            <td>
                                <div class="ms-fw-500">{{ $question->label() }}</div>
                                @if ($question->rejection_reason)
                                    <div class="text-muted ms-fs-12">{{ __('questions.mine.reason') }}: {{ $question->rejection_reason }}</div>
                                @endif
                            </td>
                            <td class="text-muted">{{ $question->category?->name }}</td>
                            <td><x-questions.status-tag :status="$question->status" /></td>
                            <td class="ms-text-right">
                                @if (in_array($question->status->value, ['draft', 'pending', 'rejected'], true))
                                    <button type="button" wire:click="withdraw('{{ $question->uuid }}')" wire:confirm="{{ __('questions.mine.withdraw_confirm') }}" class="btn ms-btn-sm-danger">{{ __('questions.mine.withdraw') }}</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted ms-empty">{{ __('questions.mine.empty') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="ms-cell-top">{{ $this->submissions->links() }}</div>
    </div>
</div>

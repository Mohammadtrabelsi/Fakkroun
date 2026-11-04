<?php

use App\Domain\Questions\SubmissionService;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Support\Livewire\EditsQuestionContent;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Submit a question')] class extends Component
{
    use EditsQuestionContent;

    public function mount(): void
    {
        $this->authorize('submit questions');
        $this->language = auth()->user()->preferredQuestionLocale();
    }

    protected function categoryFilter(Category $category): bool
    {
        return $category->allow_community_submissions;
    }

    public function remaining(): int
    {
        return app(SubmissionService::class)->remainingToday(auth()->user());
    }

    public function submit(SubmissionService $submissions): void
    {
        $this->authorize('submit questions');

        try {
            $submissions->submit(auth()->user(), $this->contentData());
        } catch (DomainActionException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('status', __('questions.submit.thanks'));
        $this->redirect(route('questions.mine'), navigate: true);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('questions.submit.title') }}</h2>
        <p class="text-muted ms-note">{{ __('questions.submit.description') }}</p>
        <p class="text-muted ms-fs-12">{{ __('questions.submit.remaining', ['count' => $this->remaining()]) }}</p>
    </div>

    @error('form')
        <div class="tag tag-outline ms-notice">{{ $message }}</div>
    @enderror

    <form wire:submit="submit" class="card ms-stack-16">
        @include('partials.questions.fields')

        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="submit">{{ __('questions.submit.send') }}</button>
            <a href="{{ route('questions.mine') }}" wire:navigate class="btn">{{ __('questions.mine.title') }}</a>
        </div>
    </form>
</div>

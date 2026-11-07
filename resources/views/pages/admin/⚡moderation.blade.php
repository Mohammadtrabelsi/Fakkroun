<?php

use App\Domain\Questions\Enums\ReportStatus;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\Models\QuestionReport;
use App\Domain\Questions\Models\QuestionTranslation;
use App\Domain\Questions\QuestionQueries;
use App\Domain\Questions\ReportService;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Moderation')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    /** questions | translations | reports */
    #[Url(history: true)]
    public string $tab = 'questions';

    /** @var array<int|string, string> keyed by question or translation id */
    public array $reasons = [];

    public function mount(): void
    {
        $this->authorize('moderate questions');
    }

    #[Computed]
    public function queue()
    {
        return app(QuestionQueries::class)->reviewQueue(auth()->user());
    }

    #[Computed]
    public function translationQueue()
    {
        return app(QuestionQueries::class)->translationQueue(auth()->user());
    }

    #[Computed]
    public function reports()
    {
        return app(ReportService::class)->open(app(QuestionQueries::class)->scopeFor(auth()->user()));
    }

    public function decide(int $questionId, string $action, ModerationService $moderation): void
    {
        $question = app(\App\Domain\Questions\QuestionService::class)->query()->findOrFail($questionId);
        $user = auth()->user();

        $this->attempt(fn () => match ($action) {
            'review' => $moderation->startReview($question, $user),
            'approve' => $moderation->approve($question, $user),
            'approve_publish' => $moderation->approve($question, $user, publish: true),
            'reject' => $moderation->reject($question, $user, (string) ($this->reasons[$questionId] ?? '')),
            'request_changes' => $moderation->requestChanges($question, $user, (string) ($this->reasons[$questionId] ?? '')),
        });
    }

    public function decideTranslation(int $translationId, bool $approve, ModerationService $moderation): void
    {
        $translation = app(\App\Domain\Questions\QuestionTranslationService::class)->query()->findOrFail($translationId);
        $user = auth()->user();

        $this->attempt(fn () => $approve
            ? $moderation->approveTranslation($translation, $user)
            : $moderation->rejectTranslation($translation, $user, (string) ($this->reasons['t'.$translationId] ?? '')));
    }

    public function resolveReport(int $reportId, bool $dismiss, ReportService $reports): void
    {
        $report = app(\App\Domain\Questions\ReportService::class)->query()->findOrFail($reportId);

        $this->attempt(fn () => $reports->resolve($report, auth()->user(), $dismiss ? ReportStatus::Dismissed : ReportStatus::Resolved));
    }

    private function attempt(callable $action): void
    {
        try {
            $action();
            $this->toast(__('questions.moderation.done'));
        } catch (\DomainException $e) {
            $this->toast($e->getMessage(), 'error');
        } catch (AuthorizationException $e) {
            $this->toast($e->getMessage() ?: __('questions.errors.cannot_approve'), 'error');
        }
    }
};
?>

<div>
    <div class="ms-mb-24">
        <h2>{{ __('questions.moderation.title') }}</h2>
        <p class="text-muted ms-note">{{ __('questions.moderation.description') }}</p>
    </div>

    <div class="ms-tabs ms-mb-24">
        @foreach (['questions', 'translations', 'reports'] as $name)
            <button type="button" wire:click="$set('tab', '{{ $name }}')" @class(['ms-locale-tab', 'active' => $tab === $name])>{{ __('questions.moderation.tab_'.$name) }}</button>
        @endforeach
    </div>

    @if ($tab === 'questions')
        @forelse ($this->queue as $question)
            @php($origin = $question->originTranslation())
            @php($meta = $question->moderationLogs->first()?->meta ?? [])
            <div class="card ms-mt16-stack-16" wire:key="mq-{{ $question->id }}">
                <div class="ms-row-between">
                    <span class="text-muted ms-fs-12">{{ $question->category?->name }}@if ($question->subcategory) › {{ $question->subcategory->name }}@endif · {{ __('questions.difficulty.'.$question->difficulty->value) }} · {{ strtoupper($question->origin_language) }} · {{ __('questions.source.'.$question->source->value) }}@if ($question->author) · {{ $question->author->name }}@endif</span>
                    <x-questions.status-tag :status="$question->status" />
                </div>
                <div class="ms-fw-500" dir="{{ config('app.available_locales.'.$question->origin_language.'.dir', 'ltr') }}">{{ $origin?->body }}</div>
                <ol class="ms-stack-16" dir="{{ config('app.available_locales.'.$question->origin_language.'.dir', 'ltr') }}">
                    @foreach ($question->choices as $choice)
                        <li @class(['ms-fw-500' => $choice->id === $question->correct_choice_id])>
                            {{ $choice->letter() }}. {{ $origin?->choiceText($choice->id) }} @if ($choice->id === $question->correct_choice_id) ✓ @endif
                        </li>
                    @endforeach
                </ol>
                @if ($origin?->explanation) <p class="text-muted ms-fs-13">{{ $origin->explanation }}</p> @endif
                <p class="text-muted ms-fs-12">{{ __('questions.fields.reference_url') }}: {{ $question->reference_url ?: ($question->reference_text ?: '—') }}</p>
                @foreach ((array) ($meta['warnings'] ?? []) as $warning)
                    <div class="tag tag-outline">{{ $warning }}</div>
                @endforeach
                @if (! empty($meta['possible_duplicates']))
                    <div class="tag tag-outline">{{ __('questions.moderation.possible_duplicates', ['count' => count($meta['possible_duplicates'])]) }}</div>
                @endif
                <x-text-input wire:model="reasons.{{ $question->id }}" type="text" :placeholder="__('questions.admin.reason')" />
                <div class="ms-row-10 ms-wrap">
                    @if ($question->status->value === 'pending')
                        <button type="button" wire:click="decide({{ $question->id }}, 'review')" class="btn">{{ __('questions.actions.review') }}</button>
                    @endif
                    <button type="button" wire:click="decide({{ $question->id }}, 'approve_publish')" class="btn btn-primary">{{ __('questions.actions.approve_publish') }}</button>
                    <button type="button" wire:click="decide({{ $question->id }}, 'approve')" class="btn">{{ __('questions.actions.approve') }}</button>
                    <button type="button" wire:click="decide({{ $question->id }}, 'request_changes')" class="btn">{{ __('questions.actions.request_changes') }}</button>
                    <button type="button" wire:click="decide({{ $question->id }}, 'reject')" class="btn ms-outline-danger">{{ __('questions.actions.reject') }}</button>
                </div>
            </div>
        @empty
            <div class="card text-muted">{{ __('questions.moderation.empty') }}</div>
        @endforelse
        <div class="ms-cell-top">{{ $this->queue->links() }}</div>
    @elseif ($tab === 'translations')
        @forelse ($this->translationQueue as $translation)
            @php($question = $translation->question)
            @php($origin = $question->originTranslation())
            <div class="card ms-mt16-stack-16" wire:key="mt-{{ $translation->id }}">
                <span class="text-muted ms-fs-12">{{ $question->category?->name }} · {{ strtoupper($question->origin_language) }} → {{ strtoupper($translation->language) }}</span>
                <div class="ms-row-10 ms-wrap">
                    @foreach ([$origin, $translation] as $side)
                        <div class="ms-flex-1 ms-minw-0" dir="{{ config('app.available_locales.'.$side?->language.'.dir', 'ltr') }}">
                            <div class="ms-fw-500">{{ $side?->body }}</div>
                            <ol>
                                @foreach ($question->choices as $choice)
                                    <li @class(['ms-fw-500' => $choice->id === $question->correct_choice_id])>{{ $choice->letter() }}. {{ $side?->choiceText($choice->id) }}</li>
                                @endforeach
                            </ol>
                        </div>
                    @endforeach
                </div>
                <x-text-input wire:model="reasons.t{{ $translation->id }}" type="text" :placeholder="__('questions.admin.reason')" />
                <div class="ms-row-10">
                    <button type="button" wire:click="decideTranslation({{ $translation->id }}, true)" class="btn btn-primary">{{ __('questions.actions.approve') }}</button>
                    <button type="button" wire:click="decideTranslation({{ $translation->id }}, false)" class="btn ms-outline-danger">{{ __('questions.actions.reject') }}</button>
                </div>
            </div>
        @empty
            <div class="card text-muted">{{ __('questions.moderation.empty') }}</div>
        @endforelse
        <div class="ms-cell-top">{{ $this->translationQueue->links() }}</div>
    @else
        @forelse ($this->reports as $report)
            <div class="card ms-mt16-stack-16" wire:key="mr-{{ $report->id }}">
                <span class="text-muted ms-fs-12">{{ __('questions.report_reason.'.$report->reason->value) }} · {{ $report->reporter?->name }} · {{ $report->created_at->diffForHumans() }}</span>
                <div class="ms-fw-500">{{ $report->question?->label() }}</div>
                @if ($report->details) <p class="text-muted">{{ $report->details }}</p> @endif
                <div class="ms-row-10 ms-wrap">
                    @can('manage questions')
                        <a href="{{ route('admin.questions.edit', $report->question) }}" wire:navigate class="btn">{{ __('questions.admin.edit') }}</a>
                    @endcan
                    <button type="button" wire:click="resolveReport({{ $report->id }}, false)" class="btn btn-primary">{{ __('questions.moderation.resolve') }}</button>
                    <button type="button" wire:click="resolveReport({{ $report->id }}, true)" class="btn">{{ __('questions.moderation.dismiss') }}</button>
                </div>
            </div>
        @empty
            <div class="card text-muted">{{ __('questions.moderation.empty') }}</div>
        @endforelse
        <div class="ms-cell-top">{{ $this->reports->links() }}</div>
    @endif
</div>

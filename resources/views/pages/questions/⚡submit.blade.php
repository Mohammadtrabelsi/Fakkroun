<?php

use App\Domain\Ai\QuestionDrafter;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\SubmissionService;
use App\Models\Category;
use App\Support\Livewire\EditsQuestionContent;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Community submission (fakkroun-design/design/Community.dc.html): write or
 * AI-draft a question, save it as a draft or send it for review, and follow
 * "Your questions". ?question=<uuid> reopens the author's own draft or a
 * question a moderator sent back.
 */
new #[Layout('components.layouts.fakkroun')] #[Title('Write a question')] class extends Component
{
    use EditsQuestionContent;

    #[Url(as: 'question')]
    public string $editing = '';

    public string $aiTopic = '';

    public string $aiFact = '';

    public ?string $notice = null;

    public function mount(SubmissionService $submissions): void
    {
        $this->authorize('submit questions');
        $this->language = auth()->user()->preferredQuestionLocale();

        if ($this->editing !== '' && ($question = $this->editableQuestion())) {
            $this->fillFrom($question);
        }
    }

    private function editableQuestion(): ?Question
    {
        $question = app(\App\Domain\Questions\QuestionService::class)->query()->where('uuid', $this->editing)->first();

        try {
            return $question ? app(SubmissionService::class)->editable(auth()->user(), $question) : null;
        } catch (AuthorizationException|\DomainException) {
            $this->editing = '';

            return null;
        }
    }

    private function fillFrom(Question $question): void
    {
        $question->loadMissing(['choices', 'translations']);
        $ids = $question->choices->pluck('id')->values()->all();
        $origin = $question->originTranslation();

        $this->categoryId = (string) $question->category_id;
        $this->subcategoryId = (string) ($question->subcategory_id ?? '');
        $this->difficulty = $question->difficulty->value;
        $this->language = $question->origin_language;
        $this->body = (string) $origin?->body;
        $this->choices = array_map(fn ($id) => (string) $origin?->choiceText($id), $ids);
        $this->correct = (string) max(0, (int) array_search($question->correct_choice_id, $ids, true));
        $this->explanation = (string) $origin?->explanation;
        $this->referenceUrl = (string) $question->reference_url;
        $this->referenceText = (string) $question->reference_text;
        $this->notice = $question->rejection_reason;
    }

    protected function categoryFilter(Category $category): bool
    {
        return $category->allow_community_submissions;
    }

    #[Computed]
    public function remaining(): int
    {
        return app(SubmissionService::class)->remainingToday(auth()->user());
    }

    #[Computed]
    public function mine()
    {
        return app(SubmissionService::class)->mine(auth()->user(), 6);
    }

    #[Computed]
    public function aiEnabled(): bool
    {
        return app(QuestionDrafter::class)->enabled();
    }

    public function draftWithAi(QuestionDrafter $drafter): void
    {
        $this->authorize('submit questions');
        $this->validate(['aiTopic' => ['required', 'string', 'max:120'], 'aiFact' => ['required', 'string', 'max:500']]);

        try {
            $draft = $drafter->draft($this->aiTopic, $this->aiFact, $this->language, $this->difficulty, auth()->user());
        } catch (\DomainException $e) {
            $this->addError('aiTopic', $e->getMessage());

            return;
        }

        // A starting point only: the player edits it and it is moderated like any other question.
        $this->body = $draft['body'];
        $this->choices = $draft['choices'];
        $this->correct = (string) $draft['correct'];
        $this->explanation = (string) ($draft['explanation'] ?? '');
    }

    public function saveDraft(SubmissionService $submissions): void
    {
        $this->persist($submissions, draft: true);
    }

    public function submit(SubmissionService $submissions): void
    {
        $this->persist($submissions, draft: false);
    }

    private function persist(SubmissionService $submissions, bool $draft): void
    {
        $this->authorize('submit questions');
        $existing = $this->editing !== '' ? $this->editableQuestion() : null;

        try {
            $question = $draft
                ? $submissions->saveDraft(auth()->user(), $this->contentData(), $existing)
                : $submissions->submit(auth()->user(), $this->contentData(), $existing);
        } catch (\DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        session()->flash('status', $draft ? __('questions.submit.draft_saved') : __('questions.submit.thanks'));

        $this->redirect($draft ? route('questions.submit', ['question' => $question->uuid]) : route('questions.submit'), navigate: true);
    }

    /**
     * View-ready data for the form and the "your questions" list.
     *
     * @return array<string, mixed>
     */
    public function with(): array
    {
        $language = $this->language ?: app()->getLocale();
        $letters = $language === 'ar' ? ['أ', 'ب', 'ج', 'د'] : ['A', 'B', 'C', 'D'];

        return [
            'dir' => (string) config('app.available_locales.'.$language.'.dir', 'ltr'),
            'max' => (int) config('fakkroun.questions.community_body_max', 160),
            'bodyLength' => mb_strlen($this->body),
            'answers' => array_map(fn (int $i): array => [
                'index' => $i,
                'letter' => $letters[$i],
                'isCorrect' => (string) $i === (string) $this->correct,
            ], range(0, 3)),
            'mineRows' => $this->mine->map(function ($question): array {
                $status = $question->status->value;
                [$tone, $label] = match ($status) {
                    'published' => ['live', __('questions.community.live')],
                    'draft' => ['draft', __('questions.status.draft')],
                    'changes_requested', 'rejected' => ['attention', __('questions.status.'.$status)],
                    default => ['review', __('questions.community.in_review')],
                };

                return [
                    'id' => $question->id,
                    'tone' => $tone,
                    'label' => $label,
                    'title' => Str::limit($question->label(), 110),
                    'meta' => $question->category?->name.' · '.($status === 'published'
                        ? __('questions.community.played', ['count' => $question->times_played])
                        : __('questions.community.submitted', ['date' => $question->created_at->isoFormat('ll')])),
                    'editUrl' => in_array($status, ['draft', 'changes_requested', 'rejected'], true)
                        ? route('questions.submit', ['question' => $question->uuid])
                        : null,
                    'editLabel' => $question->rejection_reason ? __('questions.community.read_note') : __('questions.admin.edit'),
                ];
            })->all(),
        ];
    }
};
?>

<div class="fk-container flex flex-col gap-8 pt-12 pb-20">
    <div class="flex max-w-[44rem] flex-col gap-2">
        <h1 class="fk-h2 m-0 text-[clamp(36px,9vw,52px)] leading-none">{{ __('questions.community.title') }}</h1>
        <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('questions.community.lead') }}</p>
        <p class="m-0 text-sm text-ink-soft">{{ __('questions.submit.remaining', ['count' => $this->remaining]) }}</p>
    </div>

    @if (session('status'))
        <p role="status" class="m-0 rounded-2xl bg-tint-green p-4 font-semibold text-[#1F6A2C]">{{ session('status') }}</p>
    @endif

    <div class="flex flex-wrap items-start gap-6">
        <form wire:submit="submit" class="flex min-w-0 flex-[999_1_600px] flex-col gap-6 rounded-[28px] border border-sand-300 bg-paper p-[clamp(20px,3vw,36px)]">
            @if ($notice)
                <div role="note" class="rounded-2xl bg-tint-sun p-4 text-[#5A3618]"><b>{{ __('questions.community.moderator_note') }}</b> {{ $notice }}</div>
            @endif
            @error('form') <p role="alert" class="m-0 rounded-xl bg-tint-sun p-3 font-semibold text-[#5A3618]">{{ $message }}</p> @enderror

            @if ($this->aiEnabled)
                <div class="flex flex-col gap-3 rounded-[20px] bg-tint-sea p-5">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex flex-col"><b>{{ __('questions.community.ai_title') }}</b><span class="text-[15px] text-ink-muted">{{ __('questions.community.ai_body') }}</span></div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <input wire:model="aiTopic" aria-label="{{ __('questions.community.ai_topic') }}" placeholder="{{ __('questions.community.ai_topic') }}" class="min-h-11 min-w-0 flex-[1_1_180px] rounded-xl border-2 border-sand-400 bg-white px-3">
                        <input wire:model="aiFact" aria-label="{{ __('questions.community.ai_fact') }}" placeholder="{{ __('questions.community.ai_fact') }}" class="min-h-11 min-w-0 flex-[2_1_260px] rounded-xl border-2 border-sand-400 bg-white px-3">
                        <button type="button" wire:click="draftWithAi" wire:loading.attr="disabled" wire:target="draftWithAi" class="fk-btn bg-ink text-sand">{{ __('questions.community.ai_button') }}</button>
                    </div>
                    @error('aiTopic') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
                    @error('aiFact') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
                </div>
            @endif

            <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(180px,100%),1fr))]">
                <div class="flex flex-col gap-2">
                    <label for="cat" class="font-semibold">{{ __('questions.fields.category') }}</label>
                    <select id="cat" wire:model.live="categoryId" class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-3">
                        <option value="">{{ __('questions.fields.choose') }}</option>
                        @foreach ($this->categoryOptions as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach
                    </select>
                    @error('category_id') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
                </div>
                <div class="flex flex-col gap-2">
                    <label for="diff" class="font-semibold">{{ __('questions.fields.difficulty') }}</label>
                    <select id="diff" wire:model="difficulty" class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-3">
                        @foreach (\App\Domain\Questions\Enums\Difficulty::cases() as $level)<option value="{{ $level->value }}">{{ __('questions.difficulty.'.$level->value) }}</option>@endforeach
                    </select>
                </div>
                <div class="flex flex-col gap-2">
                    <label for="lang" class="font-semibold">{{ __('questions.fields.language') }}</label>
                    <select id="lang" wire:model.live="language" @disabled($editing !== '') class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-3">
                        @foreach (config('app.available_locales') as $code => $meta)<option value="{{ $code }}" lang="{{ $code }}">{{ $meta['label'] }}</option>@endforeach
                    </select>
                </div>
            </div>

            <div class="flex flex-col gap-2" x-data="{ n: {{ $bodyLength }} }">
                <label for="q" class="font-semibold">{{ __('questions.fields.body') }}</label>
                <textarea id="q" wire:model="body" rows="3" maxlength="{{ $max + 40 }}" dir="{{ $dir }}" x-on:input="n = $event.target.value.length" aria-describedby="q-hint" class="rounded-[14px] border-2 border-sand-400 bg-white p-3 text-[17px]"></textarea>
                <span id="q-hint" class="text-sm text-ink-soft">{{ __('questions.community.length_hint', ['max' => $max]) }} · <span x-text="n" :class="n > {{ $max }} ? 'font-bold text-wrong' : ''"></span>/{{ $max }}</span>
                @error('body') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <fieldset class="m-0 flex min-w-0 flex-col gap-2.5 border-0 p-0" dir="{{ $dir }}">
                <legend class="mb-2.5 p-0 font-semibold">{{ __('questions.community.answers') }} <span class="font-normal text-ink-soft">{{ __('questions.community.answers_hint') }}</span></legend>
                @foreach ($answers as $answer)
                    <label @class(['flex min-w-0 items-center gap-2.5 rounded-2xl border-2 p-2.5', 'border-correct bg-tint-green' => $answer['isCorrect'], 'border-sand-300 bg-white' => ! $answer['isCorrect']]) wire:key="answer-{{ $answer['index'] }}">
                        <input type="radio" wire:model.live="correct" value="{{ $answer['index'] }}" class="h-5 w-5 flex-none accent-correct" aria-label="{{ __('questions.fields.mark_correct', ['letter' => $answer['letter']]) }}">
                        <span class="flex h-9 w-9 flex-none items-center justify-center rounded-[10px] bg-sand-200 font-display text-lg font-extrabold">{{ $answer['letter'] }}</span>
                        <input wire:model="choices.{{ $answer['index'] }}" aria-label="{{ __('questions.community.answer_n', ['letter' => $answer['letter']]) }}" class="min-h-11 w-0 min-w-0 flex-1 rounded-xl border-0 bg-transparent px-2 text-base">
                        @if ($answer['isCorrect'])<span class="flex-none text-sm font-semibold text-correct max-[400px]:sr-only">{{ __('play.game2.correct_tag') }}</span>@endif
                    </label>
                    @error('choices.'.$answer['index']) <span class="text-sm text-wrong">{{ $message }}</span> @enderror
                @endforeach
                @error('choices') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </fieldset>

            <div class="flex flex-col gap-2">
                <label for="why" class="font-semibold">{{ __('questions.community.explanation') }}</label>
                <textarea id="why" wire:model="explanation" rows="3" dir="{{ $dir }}" class="rounded-[14px] border-2 border-sand-400 bg-white p-3"></textarea>
                @error('explanation') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-col gap-2">
                <label for="src" class="font-semibold">{{ __('questions.fields.reference_url') }}</label>
                <input id="src" type="url" wire:model="referenceUrl" placeholder="https://" aria-describedby="src-hint" class="min-h-[52px] rounded-[14px] border-2 border-sand-400 bg-white px-4">
                <span id="src-hint" class="text-sm text-ink-soft">{{ __('questions.community.source_hint') }}</span>
                @error('reference_url') <span class="text-sm text-wrong">{{ $message }}</span> @enderror
            </div>

            <div class="flex flex-wrap justify-end gap-3 border-t border-sand-300 pt-6">
                <button type="button" wire:click="saveDraft" class="fk-btn fk-btn-outline min-h-[52px] rounded-[14px]">{{ __('questions.community.save_draft') }}</button>
                <button type="submit" class="fk-btn fk-btn-primary min-h-[52px] rounded-[14px] px-6" wire:loading.attr="disabled" wire:target="submit">{{ __('questions.submit.send') }}</button>
            </div>
        </form>

        <aside class="flex min-w-0 flex-[1_1_340px] flex-col gap-6">
            <section aria-labelledby="flow-h" class="flex flex-col gap-4 rounded-[28px] bg-sea-deep p-7 text-sand">
                <h2 id="flow-h" class="m-0 text-[22px] font-semibold">{{ __('questions.community.next_title') }}</h2>
                <ol class="m-0 flex list-none flex-col gap-3 p-0">
                    @foreach ([1, 2, 3] as $step)
                        <li class="flex items-start gap-3"><span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-sun font-bold text-ink">{{ $step }}</span><span><b>{{ __('questions.community.next_'.$step) }}</b><br><span class="text-[15px] text-[#C9D3D0]">{{ __('questions.community.next_'.$step.'_body') }}</span></span></li>
                    @endforeach
                </ol>
            </section>
            <section aria-labelledby="mine-h" class="flex flex-col gap-3.5 rounded-[28px] border border-sand-300 bg-paper p-6">
                <h2 id="mine-h" class="m-0 text-[22px] font-semibold">{{ __('questions.community.yours') }}</h2>
                <ul class="m-0 flex list-none flex-col gap-2.5 p-0">
                    @forelse ($mineRows as $row)
                        <li class="flex flex-col gap-1 rounded-2xl bg-sand p-3" wire:key="mine-{{ $row['id'] }}">
                            <span class="fk-status" data-tone="{{ $row['tone'] }}">{{ $row['label'] }}</span>
                            <b class="text-[15px] leading-snug break-words">{{ $row['title'] }}</b>
                            <span class="text-[13px] text-ink-soft">{{ $row['meta'] }}</span>
                            @if ($row['editUrl'])
                                <a href="{{ $row['editUrl'] }}" wire:navigate class="fk-link text-sm font-semibold">{{ $row['editLabel'] }}</a>
                            @endif
                        </li>
                    @empty
                        <li class="text-[15px] text-ink-soft">{{ __('questions.mine.empty') }}</li>
                    @endforelse
                </ul>
                <a href="{{ route('questions.mine') }}" wire:navigate class="fk-link text-sm font-semibold">{{ __('questions.mine.title') }}</a>
            </section>
        </aside>
    </div>
</div>

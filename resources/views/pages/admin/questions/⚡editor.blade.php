<?php

use App\Domain\Questions\Enums\QuestionSource;
use App\Domain\Questions\Enums\QuestionStatus;
use App\Domain\Questions\ModerationService;
use App\Domain\Questions\Models\Question;
use App\Domain\Questions\QuestionService;
use App\Support\Livewire\EditsQuestionContent;
use App\Support\Livewire\InteractsWithToasts;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Create (no question) or edit a question: origin content, translations,
 * lifecycle actions and history.
 */
new #[Layout('layouts::app')] #[Title('Question')] class extends Component
{
    use EditsQuestionContent;
    use InteractsWithToasts;

    public ?Question $question = null;

    /** @var array<string, array{body: string, choices: array<int, string>, explanation: string}> */
    public array $translations = [];

    public string $rejectReason = '';

    public string $sponsorCampaign = '';

    /** @var array<int, string> */
    public array $warnings = [];

    public function mount(?Question $question = null): void
    {
        $this->authorize('manage questions');
        $this->language = app()->getLocale();

        if ($question?->exists) {
            $this->question = $question;
            $this->sponsorCampaign = (string) ($question->campaign_id ?? '');
            $this->fillFromQuestion();
        }
    }

    private function fillFromQuestion(): void
    {
        $question = $this->question->fresh(['choices', 'translations']);
        $this->question = $question;
        $choiceIds = $question->choices->pluck('id')->values()->all();
        $origin = $question->originTranslation();

        $this->categoryId = (string) $question->category_id;
        $this->subcategoryId = (string) ($question->subcategory_id ?? '');
        $this->difficulty = $question->difficulty->value;
        $this->language = $question->origin_language;
        $this->body = (string) $origin?->body;
        $this->choices = array_map(fn ($id) => (string) $origin?->choiceText($id), $choiceIds);
        $this->correct = (string) max(0, (int) array_search($question->correct_choice_id, $choiceIds, true));
        $this->explanation = (string) $origin?->explanation;
        $this->referenceUrl = (string) $question->reference_url;
        $this->referenceText = (string) $question->reference_text;

        foreach (array_keys(config('app.available_locales')) as $code) {
            if ($code === $question->origin_language) {
                continue;
            }

            $translation = $question->translation($code);
            $this->translations[$code] = [
                'body' => (string) $translation?->body,
                'choices' => array_map(fn ($id) => (string) $translation?->choiceText($id), $choiceIds),
                'explanation' => (string) $translation?->explanation,
            ];
        }
    }

    public function save(QuestionService $questions): void
    {
        $this->authorize('manage questions');
        $data = $this->contentData();

        if ($this->question === null) {
            $this->warnings = $questions->inspect($data)['warnings'];
            $question = $questions->create($data, auth()->user(), QuestionSource::Admin);
            session()->flash('status', __('questions.admin.created'));
            $this->redirect(route('admin.questions.edit', $question), navigate: true);

            return;
        }

        $this->warnings = $questions->inspect($data, $this->question->id)['warnings'];
        $questions->updateContent($this->question, $data, auth()->user());
        $this->fillFromQuestion();
        $this->toast(__('questions.admin.saved'));
    }

    public function saveTranslation(string $code, QuestionService $questions): void
    {
        $this->authorize('manage questions');
        $text = $this->translations[$code] ?? null;

        if ($this->question === null || $text === null) {
            return;
        }

        try {
            $questions->upsertTranslation($this->question, $code, $text, auth()->user());
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError("translations.{$code}.{$key}", $messages[0]);
            }

            return;
        }

        $this->fillFromQuestion();
        $this->toast(__('questions.admin.translation_saved'));
    }

    public function act(string $action, ModerationService $moderation): void
    {
        $this->authorize('manage questions');
        $user = auth()->user();

        try {
            match ($action) {
                'submit' => $moderation->submit($this->question, $user),
                'approve' => $moderation->approve($this->question, $user),
                'approve_publish' => $moderation->approve($this->question, $user, publish: true),
                'publish' => $moderation->publish($this->question, $user),
                'unpublish' => $moderation->unpublish($this->question, $user),
                'reject' => $moderation->reject($this->question, $user, $this->rejectReason),
                'request_changes' => $moderation->requestChanges($this->question, $user, $this->rejectReason),
            };
        } catch (\DomainException|AuthorizationException $e) {
            $this->toast($e->getMessage() ?: __('questions.errors.cannot_approve'), 'error');

            return;
        }

        $this->rejectReason = '';
        $this->fillFromQuestion();
        $this->toast(__('questions.admin.status_changed', ['status' => __('questions.status.'.$this->question->status->value)]));
    }

    public function reviewTranslation(string $code, bool $approve, ModerationService $moderation): void
    {
        $translation = $this->question?->translation($code);

        if ($translation === null) {
            return;
        }

        try {
            $approve
                ? $moderation->approveTranslation($translation, auth()->user())
                : $moderation->rejectTranslation($translation, auth()->user(), $this->rejectReason ?: __('questions.admin.translation_rejected_default'));
        } catch (\DomainException|AuthorizationException $e) {
            $this->toast($e->getMessage() ?: __('questions.errors.cannot_approve'), 'error');

            return;
        }

        $this->fillFromQuestion();
    }

    /**
     * Sponsored question assignment (spec §18.3); never changes the content.
     */
    public function saveSponsor(\App\Domain\Advertising\SponsoredQuestionService $sponsored): void
    {
        $this->authorize('manage advertising');

        $campaign = $this->sponsorCampaign === '' ? null
            : app(\App\Domain\Advertising\CampaignService::class)->query()->findOrFail((int) $this->sponsorCampaign);

        $this->question = $sponsored->assign($this->question, $campaign, auth()->user());
        $this->toast(__('advertising.sponsor_saved'));
    }

    /**
     * @return array<int, string>
     */
    public function availableActions(): array
    {
        return match ($this->question?->status) {
            QuestionStatus::Draft => ['submit', 'approve', 'approve_publish'],
            QuestionStatus::Pending, QuestionStatus::UnderReview => ['approve', 'approve_publish', 'request_changes', 'reject'],
            QuestionStatus::Approved => ['publish', 'reject'],
            QuestionStatus::Published => ['unpublish'],
            QuestionStatus::Unpublished => ['publish'],
            QuestionStatus::Rejected, QuestionStatus::ChangesRequested => ['submit'],
            default => [],
        };
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <a href="{{ route('admin.questions.index') }}" wire:navigate class="text-muted ms-fs-13">&larr; {{ __('questions.admin.title') }}</a>
        <h2 class="ms-mt-8">{{ $question ? __('questions.admin.edit_title') : __('questions.admin.new') }}</h2>
        @if ($question)
            <p class="text-muted ms-note">
                <x-questions.status-tag :status="$question->status" />
                v{{ $question->version }} · {{ __('questions.source.'.$question->source->value) }}
                · {{ __('questions.admin.playable_in') }}: {{ implode(', ', array_map('strtoupper', app(\App\Domain\Questions\QuestionPool::class)->languages($question))) ?: '—' }}
            </p>
        @endif
    </div>

    @if (session('status'))
        <div data-autohide="3000" class="tag tag-accent ms-notice">{{ session('status') }}</div>
    @endif

    @foreach ($warnings as $warning)
        <div class="tag tag-outline ms-notice">{{ $warning }}</div>
    @endforeach

    <form wire:submit="save" class="card ms-stack-16">
        @include('partials.questions.fields', ['languageLocked' => $question !== null])

        @if ($question && in_array($question->status->value, ['approved', 'published', 'unpublished', 'under_review'], true))
            <p class="text-muted ms-fs-12">{{ __('questions.admin.revalidation_notice') }}</p>
        @endif

        <div><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('categories.save_changes') }}</button></div>
    </form>

    @if ($question)
        <div class="card ms-mt16-stack-16">
            <h3 class="ms-fs-18">{{ __('questions.admin.lifecycle') }}</h3>
            <div class="field ms-mb-0">
                <x-input-label for="rejectReason" :value="__('questions.admin.reason')" />
                <x-text-input wire:model="rejectReason" id="rejectReason" type="text" />
            </div>
            <div class="ms-row-10 ms-wrap">
                @foreach ($this->availableActions() as $action)
                    <button type="button" wire:click="act('{{ $action }}')" @class(['btn', 'btn-primary' => in_array($action, ['approve_publish', 'publish'], true), 'ms-outline-danger' => in_array($action, ['reject', 'unpublish'], true)])>
                        {{ __('questions.actions.'.$action) }}
                    </button>
                @endforeach
            </div>
        </div>

        @can('manage advertising')
            <form wire:submit="saveSponsor" class="card ms-mt16-stack-16">
                <h3 class="ms-fs-18">{{ __('advertising.sponsorship') }}</h3>
                <p class="text-muted ms-fs-13">{{ __('advertising.sponsorship_hint') }}</p>
                <select wire:model="sponsorCampaign" class="input" aria-label="{{ __('advertising.campaign') }}">
                    <option value="">{{ __('advertising.not_sponsored') }}</option>
                    @foreach (app(\App\Domain\Advertising\CampaignService::class)->query()->with('advertiser')->whereNotIn('status', ['rejected', 'completed'])->orderBy('name')->get() as $c)
                        <option value="{{ $c->id }}">{{ $c->advertiser->name }} — {{ $c->name }}</option>
                    @endforeach
                </select>
                <div><button type="submit" class="btn">{{ __('categories.save_changes') }}</button></div>
            </form>
        @endcan

        @foreach ($translations as $code => $text)
            @php($translation = $question->translation($code))
            @php($dir = config('app.available_locales.'.$code.'.dir', 'ltr'))
            <form wire:submit="saveTranslation('{{ $code }}')" class="card ms-mt16-stack-16" wire:key="translation-{{ $code }}">
                <div class="ms-row-between">
                    <h3 class="ms-fs-18">{{ __('questions.admin.translation', ['language' => config('app.available_locales.'.$code.'.label')]) }}</h3>
                    @if ($translation)
                        <span class="tag {{ $translation->isApproved() ? 'tag-accent' : 'tag-neutral' }}">{{ __('questions.translation_status.'.$translation->status->value) }}</span>
                    @endif
                </div>
                <textarea wire:model="translations.{{ $code }}.body" rows="2" dir="{{ $dir }}" class="textarea" aria-label="{{ __('questions.fields.body') }}"></textarea>
                <x-input-error :message="$errors->first('translations.'.$code.'.body')" />
                @foreach (range(0, 3) as $i)
                    <div class="ms-row-10-center">
                        <span @class(['ms-fw-500', 'text-accent' => (int) $correct === $i])>{{ chr(65 + $i) }}</span>
                        <x-text-input wire:model="translations.{{ $code }}.choices.{{ $i }}" type="text" :dir="$dir" />
                    </div>
                @endforeach
                <x-input-error :message="$errors->first('translations.'.$code.'.choices') ?: $errors->first('translations.'.$code.'.choices.0')" />
                <textarea wire:model="translations.{{ $code }}.explanation" rows="2" dir="{{ $dir }}" class="textarea" aria-label="{{ __('questions.fields.explanation') }}"></textarea>
                <div class="ms-row-10 ms-wrap">
                    <button type="submit" class="btn">{{ __('questions.admin.save_translation') }}</button>
                    @if ($translation && $translation->status->value === 'pending')
                        <button type="button" wire:click="reviewTranslation('{{ $code }}', true)" class="btn btn-primary">{{ __('questions.actions.approve') }}</button>
                        <button type="button" wire:click="reviewTranslation('{{ $code }}', false)" class="btn ms-outline-danger">{{ __('questions.actions.reject') }}</button>
                    @endif
                </div>
            </form>
        @endforeach

        <div class="card ms-mt16-stack-16">
            <h3 class="ms-fs-18">{{ __('questions.admin.history') }}</h3>
            <ul class="ms-stack-16">
                @foreach ($question->moderationLogs()->with('actor:id,name')->limit(20)->get() as $log)
                    <li class="ms-fs-13">
                        <span class="ms-fw-500">{{ __('questions.log.'.$log->action) }}</span>
                        @if ($log->to_status) → {{ __('questions.status.'.$log->to_status) }} @endif
                        <span class="text-muted">· {{ $log->actor?->name ?? __('questions.admin.system') }} · {{ $log->created_at?->diffForHumans() }}</span>
                        @if ($log->reason) <div class="text-muted">{{ $log->reason }}</div> @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

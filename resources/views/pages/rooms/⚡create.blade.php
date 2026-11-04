<?php

use App\Domain\Gameplay\QuestionSelector;
use App\Domain\Gameplay\RoomService;
use App\Services\PlatformSettings;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Layout('layouts::app')] #[Title('Create a room')] class extends Component
{
    public string $name = '';

    /** @var array<int, string> */
    public array $categoryIds = [];

    public int $questionCount = 10;

    public int $secondsPerQuestion = 20;

    public string $difficulty = 'any';

    public string $language = '';

    public bool $duplicateRule = true;

    public int $maxPlayers = 10;

    public function mount(PlatformSettings $settings): void
    {
        $this->authorize('play games');

        $game = $settings->all()['game'];
        $this->name = __('play.create.default_name', ['name' => auth()->user()->name]);
        $this->questionCount = (int) $game['default_question_count'];
        $this->secondsPerQuestion = (int) $game['default_question_seconds'];
        $this->maxPlayers = (int) min($game['max_players'], 10);
        $this->duplicateRule = (bool) $settings->get('scoring.duplicate_rule_default');
        $this->language = auth()->user()->preferredQuestionLocale();
    }

    #[Computed]
    public function categories(): array
    {
        return app(RoomService::class)->categoryOptions();
    }

    #[Computed]
    public function available(): int
    {
        return app(QuestionSelector::class)->available(
            $this->language,
            array_map('intval', $this->categoryIds),
            $this->difficulty === 'any' ? null : $this->difficulty,
        );
    }

    #[Computed]
    public function limits(): array
    {
        return app(PlatformSettings::class)->all()['game'];
    }

    public function create(RoomService $rooms): void
    {
        $this->authorize('play games');

        try {
            $room = $rooms->create(auth()->user(), [
                'name' => $this->name,
                'category_ids' => array_map('intval', $this->categoryIds),
                'question_count' => $this->questionCount,
                'seconds_per_question' => $this->secondsPerQuestion,
                'difficulty' => $this->difficulty,
                'language' => $this->language,
                'duplicate_rule' => $this->duplicateRule,
                'max_players' => $this->maxPlayers,
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            foreach ($e->errors() as $key => $messages) {
                $this->addError(\Illuminate\Support\Str::before($key, '.'), $messages[0]);
            }

            return;
        } catch (\DomainException $e) {
            $this->addError('form', $e->getMessage());

            return;
        }

        $this->redirect(route('rooms.show', $room), navigate: true);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <h2>{{ __('play.create.title') }}</h2>
        <p class="text-muted ms-note">{{ __('play.create.description') }}</p>
    </div>

    @error('form')
        <div class="tag tag-outline ms-notice">{{ $message }}</div>
    @enderror

    <form wire:submit="create" class="card ms-stack-16">
        <div class="field ms-mb-0">
            <x-input-label for="name" :value="__('play.create.name')" />
            <x-text-input wire:model="name" id="name" type="text" maxlength="80" :error="$errors->first('name')" />
            <x-input-error :message="$errors->first('name')" />
        </div>

        <fieldset>
            <legend class="ds-label">{{ __('play.create.categories') }}</legend>
            <div class="ms-row-10 ms-wrap">
                @foreach ($this->categories as $id => $label)
                    <label class="ms-check-row">
                        <input type="checkbox" class="checkbox" value="{{ $id }}" wire:model.live="categoryIds"> {{ $label }}
                    </label>
                @endforeach
            </div>
            <x-input-error :message="$errors->first('category_ids')" />
        </fieldset>

        <div class="ms-row-10 ms-wrap">
            <div class="field ms-mb-0">
                <x-input-label for="language" :value="__('play.create.language')" />
                <select wire:model.live="language" id="language" class="input">
                    @foreach (config('app.available_locales') as $code => $meta)
                        <option value="{{ $code }}">{{ $meta['label'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="difficulty" :value="__('questions.fields.difficulty')" />
                <select wire:model.live="difficulty" id="difficulty" class="input">
                    <option value="any">{{ __('play.create.any_difficulty') }}</option>
                    @foreach (\App\Domain\Questions\Enums\Difficulty::cases() as $level)
                        <option value="{{ $level->value }}">{{ __('questions.difficulty.'.$level->value) }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <p class="text-muted ms-fs-12">{{ __('play.create.available', ['count' => $this->available]) }}</p>

        <div class="ms-row-10 ms-wrap">
            <div class="field ms-mb-0">
                <x-input-label for="questionCount" :value="__('play.create.question_count')" />
                <x-text-input wire:model="questionCount" id="questionCount" type="number" min="1" :max="$this->limits['max_question_count']" :error="$errors->first('question_count')" />
                <x-input-error :message="$errors->first('question_count')" />
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="secondsPerQuestion" :value="__('play.create.seconds')" />
                <x-text-input wire:model="secondsPerQuestion" id="secondsPerQuestion" type="number" :min="$this->limits['min_question_seconds']" :max="$this->limits['max_question_seconds']" :error="$errors->first('seconds_per_question')" />
                <x-input-error :message="$errors->first('seconds_per_question')" />
            </div>
            <div class="field ms-mb-0">
                <x-input-label for="maxPlayers" :value="__('play.create.max_players')" />
                <x-text-input wire:model="maxPlayers" id="maxPlayers" type="number" :min="$this->limits['min_players']" :max="$this->limits['max_players']" :error="$errors->first('max_players')" />
                <x-input-error :message="$errors->first('max_players')" />
            </div>
        </div>

        <label class="ms-option-card">
            <input wire:model="duplicateRule" type="checkbox" class="checkbox ms-mt-2">
            <span>
                <span class="ms-block-strong">{{ __('play.rule.title') }}</span>
                <span class="text-muted ms-block-fs12">{{ __('play.rule.explanation') }}</span>
            </span>
        </label>

        <div><button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="create">{{ __('play.create.submit') }}</button></div>
    </form>
</div>

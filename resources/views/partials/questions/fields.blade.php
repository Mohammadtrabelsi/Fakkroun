{{--
    Shared question fields for Livewire components exposing: categoryId,
    subcategoryId, difficulty, language (optional, see $languageLocked), body,
    choices[4], correct, explanation, referenceUrl, referenceText, and the
    computed $this->categoryOptions / $this->subcategoryOptions.
--}}

<div class="ms-row-10 ms-wrap">
    <div class="field ms-mb-0">
        <x-input-label for="categoryId" :value="__('questions.fields.category')" />
        <select wire:model.live="categoryId" id="categoryId" class="input">
            <option value="">{{ __('questions.fields.choose') }}</option>
            @foreach ($this->categoryOptions as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :message="$errors->first('category_id')" />
    </div>

    @if ($this->subcategoryOptions !== [])
        <div class="field ms-mb-0">
            <x-input-label for="subcategoryId" :value="__('questions.fields.subcategory')" />
            <select wire:model="subcategoryId" id="subcategoryId" class="input">
                <option value="">{{ __('questions.fields.none') }}</option>
                @foreach ($this->subcategoryOptions as $id => $label)
                    <option value="{{ $id }}">{{ $label }}</option>
                @endforeach
            </select>
            <x-input-error :message="$errors->first('subcategory_id')" />
        </div>
    @endif

    <div class="field ms-mb-0">
        <x-input-label for="difficulty" :value="__('questions.fields.difficulty')" />
        <select wire:model="difficulty" id="difficulty" class="input">
            @foreach (\App\Domain\Questions\Enums\Difficulty::cases() as $level)
                <option value="{{ $level->value }}">{{ __('questions.difficulty.'.$level->value) }}</option>
            @endforeach
        </select>
        <x-input-error :message="$errors->first('difficulty')" />
    </div>

    @unless ($languageLocked ?? false)
        <div class="field ms-mb-0">
            <x-input-label for="language" :value="__('questions.fields.language')" />
            <select wire:model.live="language" id="language" class="input">
                @foreach (config('app.available_locales') as $code => $meta)
                    <option value="{{ $code }}">{{ $meta['label'] }}</option>
                @endforeach
            </select>
            <x-input-error :message="$errors->first('language')" />
        </div>
    @endunless
</div>

<div class="field ms-mb-0">
    <x-input-label for="body" :value="__('questions.fields.body')" />
    <textarea wire:model="body" id="body" rows="3" dir="{{ config('app.available_locales.'.($language ?? app()->getLocale()).'.dir', 'ltr') }}" class="textarea"></textarea>
    <x-input-error :message="$errors->first('body')" />
</div>

<fieldset class="ms-stack-16">
    <legend class="ds-label">{{ __('questions.fields.choices_legend') }}</legend>
    @foreach (range(0, 3) as $i)
        <div class="ms-row-10-center" wire:key="choice-{{ $i }}">
            <input type="radio" wire:model="correct" value="{{ $i }}" id="correct-{{ $i }}" class="checkbox" aria-label="{{ __('questions.fields.mark_correct', ['letter' => chr(65 + $i)]) }}">
            <span class="ms-fw-500">{{ chr(65 + $i) }}</span>
            <x-text-input wire:model="choices.{{ $i }}" id="choice-{{ $i }}" type="text" :dir="config('app.available_locales.'.($language ?? app()->getLocale()).'.dir', 'ltr')" :error="$errors->first('choices.'.$i)" />
        </div>
        <x-input-error :message="$errors->first('choices.'.$i)" />
    @endforeach
    <x-input-error :message="$errors->first('choices') ?: $errors->first('correct')" />
    <span class="text-muted ms-block-fs12">{{ __('questions.fields.choices_hint') }}</span>
</fieldset>

<div class="field ms-mb-0">
    <x-input-label for="explanation" :value="__('questions.fields.explanation')" />
    <textarea wire:model="explanation" id="explanation" rows="2" dir="{{ config('app.available_locales.'.($language ?? app()->getLocale()).'.dir', 'ltr') }}" class="textarea"></textarea>
    <x-input-error :message="$errors->first('explanation')" />
</div>

<div class="ms-row-10 ms-wrap">
    <div class="field ms-mb-0">
        <x-input-label for="referenceUrl" :value="__('questions.fields.reference_url')" />
        <x-text-input wire:model="referenceUrl" id="referenceUrl" type="url" placeholder="https://" :error="$errors->first('reference_url')" />
        <x-input-error :message="$errors->first('reference_url')" />
    </div>
    <div class="field ms-mb-0">
        <x-input-label for="referenceText" :value="__('questions.fields.reference_text')" />
        <x-text-input wire:model="referenceText" id="referenceText" type="text" :error="$errors->first('reference_text')" />
        <x-input-error :message="$errors->first('reference_text')" />
    </div>
</div>

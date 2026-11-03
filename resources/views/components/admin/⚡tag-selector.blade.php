<?php

use App\Services\TagService;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Modelable;
use Livewire\Component;

/**
 * Reusable tag picker. Bind it to an array of tag names on the parent:
 * <livewire:admin.tag-selector wire:model="tags" />
 * Existing tags are suggested as you type; new names are created on save.
 */
new class extends Component
{
    /** @var array<int, string> */
    #[Modelable]
    public array $tags = [];

    public string $input = '';

    public string $inputId = 'tags';

    public function add(TagService $service): void
    {
        // Comma-separated input adds several tags at once.
        $this->tags = $service->normalize([...$this->tags, ...explode(',', $this->input)]);
        $this->input = '';
    }

    public function remove(int $index): void
    {
        unset($this->tags[$index]);
        $this->tags = array_values($this->tags);
    }

    #[Computed]
    public function suggestions(): array
    {
        $taken = array_map('mb_strtolower', $this->tags);

        return array_values(array_filter(
            app(TagService::class)->suggestions(trim($this->input) ?: null),
            fn (string $name) => ! in_array(mb_strtolower($name), $taken, true),
        ));
    }
};
?>

<div class="ms-stack-8">
    @if ($tags !== [])
        <div class="ms-row-10 ms-wrap" aria-live="polite">
            @foreach ($tags as $index => $tag)
                <span class="tag tag-neutral" wire:key="tag-{{ $index }}-{{ md5($tag) }}">
                    {{ $tag }}
                    <button type="button" wire:click="remove({{ $index }})" class="ms-iconbtn-plain" aria-label="{{ __('tags.remove', ['tag' => $tag]) }}">&times;</button>
                </span>
            @endforeach
        </div>
    @endif

    <div class="ms-row-10">
        <input
            wire:model.live.debounce.250ms="input"
            wire:keydown.enter.prevent="add"
            id="{{ $inputId }}"
            type="text"
            class="input"
            list="{{ $inputId }}-suggestions"
            maxlength="{{ \App\Services\TagService::MAX_LENGTH * 4 }}"
            placeholder="{{ __('tags.input_placeholder') }}"
            autocomplete="off"
        >
        <button type="button" wire:click="add" class="btn">{{ __('tags.add') }}</button>
    </div>
    <datalist id="{{ $inputId }}-suggestions">
        @foreach ($this->suggestions as $suggestion)
            <option value="{{ $suggestion }}"></option>
        @endforeach
    </datalist>
    <div class="text-muted ms-fs-12">{{ __('tags.input_help', ['max' => \App\Services\TagService::MAX_PER_MODEL]) }}</div>
</div>

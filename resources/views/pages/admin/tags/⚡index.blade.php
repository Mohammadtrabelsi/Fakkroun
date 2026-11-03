<?php

use App\Models\Tag;
use App\Services\TagService;
use App\Support\Livewire\InteractsWithToasts;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

new #[Layout('layouts::app')] #[Title('Tags')] class extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    /** Tag being edited, or null when the form creates a new tag. */
    public ?int $editingId = null;

    /** @var array<string, string> locale => name */
    public array $name = [];

    public string $activeLocale = 'en';

    public function mount(): void
    {
        $this->authorize('manage tags');
        $this->resetForm();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    #[Computed]
    public function locales(): array
    {
        return collect(config('app.available_locales'))->map(fn (array $locale) => $locale['label'])->all();
    }

    #[Computed]
    public function tags()
    {
        return app(TagService::class)->searchPaginated($this->search ?: null);
    }

    public function edit(int $tagId): void
    {
        $this->authorize('manage tags');

        $tag = Tag::findOrFail($tagId);
        $this->editingId = $tag->id;

        foreach (array_keys($this->locales) as $locale) {
            $this->name[$locale] = $tag->getTranslation('name', $locale, false) ?? '';
        }

        $this->resetValidation();
    }

    public function save(TagService $tags): void
    {
        $this->authorize('manage tags');

        $default = Tag::getLocale();
        $editing = $this->editingId ? Tag::findOrFail($this->editingId) : null;

        $this->validate([
            "name.{$default}" => ['required', 'string', 'max:'.TagService::MAX_LENGTH, function ($attribute, $value, $fail) use ($tags, $editing) {
                if ($tags->nameTaken((string) $value, $editing)) {
                    $fail(__('tags.name_taken'));
                }
            }],
            'name.*' => ['nullable', 'string', 'max:'.TagService::MAX_LENGTH],
        ], [], ["name.{$default}" => __('tags.name')]);

        if ($editing) {
            $tags->update($editing, $this->name);
            $this->toast(__('tags.updated'));
        } else {
            $tags->create($this->name);
            $this->toast(__('tags.created'));
        }

        $this->resetForm();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function deleteTag(int $tagId, TagService $tags): void
    {
        $this->authorize('manage tags');

        $tags->delete(Tag::findOrFail($tagId));

        if ($this->editingId === $tagId) {
            $this->resetForm();
        }

        $this->toast(__('tags.deleted'));
    }

    private function resetForm(): void
    {
        $this->editingId = null;
        $this->name = array_fill_keys(array_keys($this->locales), '');
        $this->activeLocale = Tag::getLocale();
        $this->resetValidation();
    }
};
?>

<div>
    <div class="ms-page-head">
        <div>
            <h2>{{ __('tags.title') }}</h2>
            <p class="text-muted ms-note">{{ __('tags.description') }}</p>
        </div>
    </div>

    <form wire:submit="save" class="card ms-stack-16 ms-mb-24">
        <h3 class="ms-mb-0">{{ $editingId ? __('tags.edit_tag') : __('tags.new_tag') }}</h3>

        <div>
            <div class="ms-tabs">
                @foreach ($this->locales as $code => $label)
                    <button
                        type="button"
                        wire:click="$set('activeLocale', '{{ $code }}')"
                        @class(['ms-locale-tab', 'active' => $activeLocale === $code])
                    >{{ $label }}</button>
                @endforeach
            </div>

            @foreach ($this->locales as $code => $label)
                <div @class(['ms-locale-panel', 'active' => $activeLocale === $code])>
                    <div class="field ms-mb-0">
                        <x-input-label :for="'tag-name-'.$code" :value="__('tags.name').' ('.$label.')'" />
                        <x-text-input
                            wire:model="name.{{ $code }}"
                            id="tag-name-{{ $code }}"
                            type="text"
                            :dir="config('app.available_locales.'.$code.'.dir', 'ltr')"
                            :error="$errors->first('name.'.$code)"
                        />
                        <x-input-error :message="$errors->first('name.'.$code)" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
                {{ $editingId ? __('tags.save') : __('tags.create') }}
            </button>
            @if ($editingId)
                <button type="button" wire:click="cancel" class="btn">{{ __('tags.cancel') }}</button>
            @endif
        </div>
    </form>

    <div class="ms-toolbar">
        <div class="ms-search-field">
            <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="rgba(var(--ink),.5)" stroke-width="1.5" class="ms-input-icon"><circle cx="11" cy="11" r="7"></circle><path d="M21 21l-4-4"></path></svg>
            <input wire:model.live.debounce.300ms="search" class="input ms-pl-32" placeholder="{{ __('tags.search_placeholder') }}" aria-label="{{ __('tags.search_placeholder') }}">
        </div>
    </div>

    <div class="card ms-p-0">
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>{{ __('tags.name') }}</th>
                        <th>{{ __('tags.slug') }}</th>
                        <th>{{ __('tags.posts') }}</th>
                        <th class="ms-text-right">{{ __('tags.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->tags as $tag)
                        <tr wire:key="tag-{{ $tag->id }}">
                            <td class="ms-fw-500">{{ $tag->name }}</td>
                            <td class="text-muted">{{ $tag->getTranslation('slug', \App\Models\Tag::getLocale(), false) }}</td>
                            <td class="text-muted">{{ $tag->posts_count }}</td>
                            <td class="ms-text-right">
                                <div class="ms-actions-end">
                                    <button type="button" wire:click="edit({{ $tag->id }})" class="btn ms-btn-sm">{{ __('tags.edit') }}</button>
                                    <button
                                        type="button"
                                        wire:click="deleteTag({{ $tag->id }})"
                                        wire:confirm="{{ __('tags.confirm_delete') }}"
                                        class="btn ms-btn-sm-danger"
                                    >{{ __('tags.delete') }}</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="text-muted ms-empty">{{ __('tags.none') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="ms-cell-top">
            {{ $this->tags->links() }}
        </div>
    </div>
</div>

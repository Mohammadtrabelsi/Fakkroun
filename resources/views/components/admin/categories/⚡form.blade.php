<?php

use App\Enums\ValidationPolicy;
use App\Exceptions\DomainActionException;
use App\Models\Category;
use App\Services\CategoryService;
use Illuminate\Validation\Rule;
use Livewire\Component;

new class extends Component
{
    public ?Category $category = null;

    public string $activeLocale = 'en';

    public array $name = [];

    public array $description = [];

    public string $slug = '';

    public bool $isActive = true;

    public string $parentId = '';

    public int $sortOrder = 0;

    public string $icon = '';

    public string $color = '';

    public string $validationPolicy = 'admin';

    public bool $requiresReference = false;

    public bool $allowCommunitySubmissions = true;

    public bool $isDefaultSelected = false;

    /** @var array<string, string> code => label, from config('app.available_locales') */
    public array $locales = [];

    public function mount(?Category $category = null): void
    {
        $this->authorize('manage categories');

        $this->locales = collect(config('app.available_locales'))->map(fn (array $meta) => $meta['label'])->all();
        $this->activeLocale = array_key_first($this->locales) ?? 'en';

        $this->category = $category;
        $this->slug = $category?->slug ?? '';
        $this->isActive = $category?->is_active ?? true;
        $this->parentId = (string) ($category?->parent_id ?? '');
        $this->sortOrder = $category?->sort_order ?? 0;
        $this->icon = (string) ($category?->icon ?? '');
        $this->color = (string) ($category?->color ?? '');
        $this->validationPolicy = $category?->validation_policy->value ?? ValidationPolicy::Admin->value;
        $this->requiresReference = $category?->requires_reference ?? false;
        $this->allowCommunitySubmissions = $category?->allow_community_submissions ?? true;
        $this->isDefaultSelected = $category?->is_default_selected ?? false;

        foreach (array_keys($this->locales) as $locale) {
            $this->name[$locale] = $category?->getTranslation('name', $locale, false) ?? '';
            $this->description[$locale] = $category?->getTranslation('description', $locale, false) ?? '';
        }
    }

    public function parentOptions(): array
    {
        return app(CategoryService::class)->parentOptions($this->category);
    }

    public function save(CategoryService $categories): void
    {
        $this->authorize('manage categories');

        $rules = [
            'description.*' => ['nullable', 'string'],
            'slug' => $this->slugRules(),
            'isActive' => ['boolean'],
            'parentId' => ['nullable', Rule::exists('categories', 'id')],
            'sortOrder' => ['integer', 'min:0', 'max:100000'],
            'icon' => ['nullable', 'string', 'max:64', 'regex:/^[a-z0-9-]+$/'],
            'color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'validationPolicy' => ['required', Rule::in(ValidationPolicy::values())],
            'requiresReference' => ['boolean'],
            'allowCommunitySubmissions' => ['boolean'],
            'isDefaultSelected' => ['boolean'],
        ];

        foreach (array_keys($this->locales) as $locale) {
            $rules["name.{$locale}"] = [$locale === 'en' ? 'required' : 'nullable', 'string', 'max:255'];
        }

        $this->validate($rules);

        $data = [
            'name' => $this->name,
            'description' => $this->description,
            'slug' => $this->slug ?: null,
            'is_active' => $this->isActive,
            'parent_id' => $this->parentId !== '' ? (int) $this->parentId : null,
            'sort_order' => $this->sortOrder,
            'icon' => $this->icon ?: null,
            'color' => $this->color ?: null,
            'validation_policy' => $this->validationPolicy,
            'requires_reference' => $this->requiresReference,
            'allow_community_submissions' => $this->allowCommunitySubmissions,
            'is_default_selected' => $this->isDefaultSelected,
        ];

        try {
            if ($this->category) {
                $categories->update($this->category, $data);
                $message = 'Category updated successfully.';
            } else {
                $categories->create($data);
                $message = 'Category created successfully.';
            }
        } catch (DomainActionException $e) {
            $this->addError('parentId', $e->getMessage());

            return;
        }

        session()->flash('status', $message);

        $this->redirect(route('admin.categories.index'), navigate: true);
    }

    /**
     * An existing category must keep a slug; a new one may leave it blank to
     * have it generated from the name.
     */
    protected function slugRules(): array
    {
        $unique = Rule::unique('categories', 'slug');

        if ($this->category) {
            $unique->ignore($this->category);
        }

        return [$this->category ? 'required' : 'nullable', 'string', 'max:255', $unique];
    }
};
?>

<form wire:submit="save" class="card ms-stack-24">
    <div>
        <div class="ms-tabs">
            @foreach ($locales as $code => $label)
                <button
                    type="button"
                    wire:click="$set('activeLocale', '{{ $code }}')"
                    @class(['ms-locale-tab', 'active' => $activeLocale === $code])
                >{{ $label }}</button>
            @endforeach
        </div>

        @foreach ($locales as $code => $label)
            <div @class(['ms-locale-panel', 'active' => $activeLocale === $code])>
                <div class="field ms-mb-0">
                    <x-input-label :for="'name-'.$code" :value="'Name ('.$label.')'" />
                    <x-text-input
                        wire:model="name.{{ $code }}"
                        id="name-{{ $code }}"
                        type="text"
                        :dir="config('app.available_locales.'.$code.'.dir', 'ltr')"
                        :error="$errors->first('name.'.$code)"
                    />
                    <x-input-error :message="$errors->first('name.'.$code)" />
                </div>

                <div class="field ms-mb-0">
                    <x-input-label :for="'description-'.$code" :value="'Description ('.$label.')'" />
                    <textarea
                        wire:model="description.{{ $code }}"
                        id="description-{{ $code }}"
                        rows="3"
                        dir="{{ config('app.available_locales.'.$code.'.dir', 'ltr') }}"
                        class="textarea"
                    ></textarea>
                </div>
            </div>
        @endforeach
    </div>

    <div class="field ms-section-top">
        <x-input-label for="slug" value="Slug" />
        <x-text-input wire:model="slug" id="slug" type="text" placeholder="{{ __('categories.slug_placeholder') }}" :error="$errors->first('slug')" />
        <x-input-error :message="$errors->first('slug')" />
    </div>

    <div class="field ms-section-top">
        <x-input-label for="parentId" :value="__('categories.parent')" />
        <select wire:model="parentId" id="parentId" class="input">
            <option value="">{{ __('categories.no_parent') }}</option>
            @foreach ($this->parentOptions() as $id => $label)
                <option value="{{ $id }}">{{ $label }}</option>
            @endforeach
        </select>
        <x-input-error :message="$errors->first('parentId')" />
    </div>

    <div class="ms-row-10 ms-wrap">
        <div class="field ms-mb-0">
            <x-input-label for="sortOrder" :value="__('categories.sort_order')" />
            <x-text-input wire:model="sortOrder" id="sortOrder" type="number" min="0" :error="$errors->first('sortOrder')" />
            <x-input-error :message="$errors->first('sortOrder')" />
        </div>
        <div class="field ms-mb-0">
            <x-input-label for="icon" :value="__('categories.icon')" />
            <x-text-input wire:model="icon" id="icon" type="text" placeholder="leaf" :error="$errors->first('icon')" />
            <span class="text-muted ms-block-fs12">{{ __('categories.icon_hint') }}</span>
            <x-input-error :message="$errors->first('icon')" />
        </div>
        <div class="field ms-mb-0">
            <x-input-label for="color" :value="__('categories.color')" />
            <x-text-input wire:model="color" id="color" type="text" placeholder="#2f855a" :error="$errors->first('color')" />
            <x-input-error :message="$errors->first('color')" />
        </div>
    </div>

    <div class="ms-section-top ms-stack-16">
        <h3 class="ms-fs-18">{{ __('categories.question_rules') }}</h3>

        <div class="field ms-mb-0">
            <x-input-label for="validationPolicy" :value="__('categories.validation_policy')" />
            <select wire:model="validationPolicy" id="validationPolicy" class="input">
                @foreach (\App\Enums\ValidationPolicy::cases() as $policy)
                    <option value="{{ $policy->value }}">{{ __('categories.policy_'.$policy->value) }}</option>
                @endforeach
            </select>
            <x-input-error :message="$errors->first('validationPolicy')" />
        </div>

        <label class="ms-option-card">
            <input wire:model="requiresReference" type="checkbox" class="checkbox ms-mt-2">
            <span>
                <span class="ms-block-strong">{{ __('categories.requires_reference') }}</span>
                <span class="text-muted ms-block-fs12">{{ __('categories.requires_reference_hint') }}</span>
            </span>
        </label>

        <label class="ms-option-card">
            <input wire:model="allowCommunitySubmissions" type="checkbox" class="checkbox ms-mt-2">
            <span>
                <span class="ms-block-strong">{{ __('categories.allow_community_submissions') }}</span>
                <span class="text-muted ms-block-fs12">{{ __('categories.allow_community_submissions_hint') }}</span>
            </span>
        </label>

        <label class="ms-option-card">
            <input wire:model="isDefaultSelected" type="checkbox" class="checkbox ms-mt-2">
            <span>
                <span class="ms-block-strong">{{ __('categories.default_selected') }}</span>
                <span class="text-muted ms-block-fs12">{{ __('categories.default_selected_hint') }}</span>
            </span>
        </label>
    </div>

    <label class="ms-option-card">
        <input wire:model="isActive" type="checkbox" class="checkbox ms-mt-2">
        <span>
            <span class="ms-block-strong">{{ __('categories.active') }}</span>
            <span class="text-muted ms-block-fs12">{{ __('categories.inactive_categories_stay_hidden_from_public_listings') }}</span>
        </span>
    </label>

    <div class="ms-row-10">
        <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">
            {{ $category ? __('categories.save_changes') : __('categories.create_category') }}
        </button>
        <a href="{{ route('admin.categories.index') }}" wire:navigate class="btn">{{ __('categories.cancel') }}</a>
    </div>
</form>

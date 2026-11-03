<?php

use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PostService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('New post')] class extends Component
{
    use WithFileUploads;

    public string $activeLocale = 'en';

    public array $title = [];

    public array $excerpt = [];

    public array $body = [];

    public string $slug = '';

    public string $status = Post::STATUS_DRAFT;

    public ?int $categoryId = null;

    /** @var array<int, string> */
    public array $tags = [];

    public $cover = null;

    public array $locales = ['en' => 'English', 'ar' => 'العربية'];

    public function mount(): void
    {
        $this->authorize('create', Post::class);

        foreach (array_keys($this->locales) as $locale) {
            $this->title[$locale] = '';
            $this->excerpt[$locale] = '';
            $this->body[$locale] = '';
        }
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->options();
    }

    public function save(PostService $posts): void
    {
        $this->authorize('create', Post::class);

        $this->validate([
            'title.en' => ['required', 'string', 'max:255'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'excerpt.*' => ['nullable', 'string'],
            'body.en' => ['required', 'string'],
            'body.ar' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:posts,slug'],
            'status' => ['required', Rule::in(array_keys(Post::statuses()))],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'tags' => ['array', 'max:'.\App\Services\TagService::MAX_PER_MODEL],
            'tags.*' => ['string', 'max:'.\App\Services\TagService::MAX_LENGTH],
            'cover' => ['nullable', 'image', 'max:2048'],
        ]);

        $posts->create([
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'slug' => $this->slug ?: null,
            'status' => $this->status,
            'category_id' => $this->categoryId,
            'tags' => $this->tags,
            'user_id' => auth()->id(),
            'cover_image' => $this->cover ? $posts->storeCover($this->cover) : null,
        ]);

        session()->flash('status', 'Post created successfully.');

        $this->redirect(route('admin.posts.index'), navigate: true);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <a href="{{ route('admin.posts.index') }}" wire:navigate class="text-muted ms-fs-13">&larr; {{ __('posts.back_to_posts') }}</a>
        <h2 class="ms-mt-8">{{ __('posts.new_post') }}</h2>
        <p class="text-muted ms-note">{{ __('posts.write_a_new_post') }}</p>
    </div>

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
                        <x-input-label :for="'title-'.$code" :value="'Title ('.$label.')'" />
                        <x-text-input
                            wire:model="title.{{ $code }}"
                            id="title-{{ $code }}"
                            type="text"
                            :dir="$code === 'ar' ? 'rtl' : 'ltr'"
                            :error="$errors->first('title.'.$code)"
                        />
                        <x-input-error :message="$errors->first('title.'.$code)" />
                    </div>

                    <div class="field ms-mb-0">
                        <x-input-label :for="'excerpt-'.$code" :value="'Excerpt ('.$label.')'" />
                        <textarea
                            wire:model="excerpt.{{ $code }}"
                            id="excerpt-{{ $code }}"
                            rows="2"
                            dir="{{ $code === 'ar' ? 'rtl' : 'ltr' }}"
                            class="textarea"
                        ></textarea>
                    </div>

                    <div class="field ms-mb-0">
                        <x-input-label :for="'body-'.$code" :value="'Body ('.$label.')'" />
                        <textarea
                            wire:model="body.{{ $code }}"
                            id="body-{{ $code }}"
                            rows="8"
                            dir="{{ $code === 'ar' ? 'rtl' : 'ltr' }}"
                            class="textarea"
                        ></textarea>
                        <x-input-error :message="$errors->first('body.'.$code)" />
                    </div>
                </div>
            @endforeach
        </div>

        <div class="ms-section-top-stack">
            <div class="field ms-mb-0">
                <x-input-label for="slug" value="Slug" />
                <x-text-input wire:model="slug" id="slug" type="text" placeholder="{{ __('posts.slug_placeholder') }}" :error="$errors->first('slug')" />
                <x-input-error :message="$errors->first('slug')" />
            </div>

            <div class="field ms-mb-0">
                <x-input-label for="categoryId" value="Category" />
                <select wire:model="categoryId" id="categoryId" class="input">
                    <option value="">{{ __('posts.select_category') }}</option>
                    @foreach ($this->categories as $id => $name)
                        <option value="{{ $id }}">{{ $name }}</option>
                    @endforeach
                </select>
                <x-input-error :message="$errors->first('categoryId')" />
            </div>

            <div class="field ms-mb-0">
                <x-input-label for="tags" :value="__('tags.label')" />
                <livewire:admin.tag-selector wire:model="tags" />
                <x-input-error :message="$errors->first('tags') ?: $errors->first('tags.*')" />
            </div>

            <div class="field ms-mb-0">
                <x-input-label for="status" value="Status" />
                <select wire:model="status" id="status" class="input">
                    @foreach (\App\Models\Post::statuses() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <x-input-error :message="$errors->first('status')" />
            </div>

            <div class="field ms-mb-0">
                <x-input-label for="cover" value="Cover image" />
                <input type="file" wire:model="cover" id="cover" accept="image/*" class="input">
                <div wire:loading wire:target="cover" class="text-muted ms-fs-12">Uploading…</div>
                <x-input-error :message="$errors->first('cover')" />
            </div>
        </div>

        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('posts.create_post') }}</button>
            <a href="{{ route('admin.posts.index') }}" wire:navigate class="btn">{{ __('posts.cancel') }}</a>
        </div>
    </form>
</div>

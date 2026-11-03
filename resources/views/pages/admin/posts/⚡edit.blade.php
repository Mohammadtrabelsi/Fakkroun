<?php

use App\Services\CategoryService;
use App\Services\PostService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use App\Models\Post;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Layout('layouts::app')] #[Title('Edit post')] class extends Component
{
    use WithFileUploads;

    public Post $post;

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

    public bool $removeCover = false;

    public array $locales = ['en' => 'English', 'ar' => 'العربية'];

    public function mount(Post $post): void
    {
        $this->authorize('update', $post);

        $this->post = $post;
        $this->slug = $post->slug;
        $this->status = $post->status;
        $this->categoryId = $post->category_id;
        $this->tags = $post->tags->map(fn ($tag) => $tag->getTranslation('name', \App\Models\Tag::getLocale()))->all();

        foreach (array_keys($this->locales) as $locale) {
            $this->title[$locale] = $post->getTranslation('title', $locale, false) ?? '';
            $this->excerpt[$locale] = $post->getTranslation('excerpt', $locale, false) ?? '';
            $this->body[$locale] = $post->getTranslation('body', $locale, false) ?? '';
        }
    }

    #[Computed]
    public function categories(): array
    {
        return app(CategoryService::class)->options();
    }

    public function save(PostService $posts): void
    {
        $this->authorize('update', $this->post);

        $this->validate([
            'title.en' => ['required', 'string', 'max:255'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'excerpt.*' => ['nullable', 'string'],
            'body.en' => ['required', 'string'],
            'body.ar' => ['nullable', 'string'],
            'slug' => ['required', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($this->post->id)],
            'status' => ['required', Rule::in(array_keys(Post::statuses()))],
            'categoryId' => ['nullable', 'exists:categories,id'],
            'tags' => ['array', 'max:'.\App\Services\TagService::MAX_PER_MODEL],
            'tags.*' => ['string', 'max:'.\App\Services\TagService::MAX_LENGTH],
            'cover' => ['nullable', 'image', 'max:2048'],
        ]);

        $posts->update($this->post, [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'body' => $this->body,
            'slug' => $this->slug,
            'status' => $this->status,
            'category_id' => $this->categoryId,
            'tags' => $this->tags,
            'cover_image' => $this->cover
                ? $posts->storeCover($this->cover)
                : ($this->removeCover ? null : $this->post->cover_image),
        ]);

        session()->flash('status', 'Post updated successfully.');

        $this->redirect(route('admin.posts.index'), navigate: true);
    }
};
?>

<div class="ms-mw-720">
    <div class="ms-mb-24">
        <a href="{{ route('admin.posts.index') }}" wire:navigate class="text-muted ms-fs-13">&larr; {{ __('posts.back_to_posts') }}</a>
        <h2 class="ms-mt-8">{{ __('posts.edit_post') }}</h2>
        <p class="text-muted ms-note">{{ __('posts.update_post_content') }}</p>
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
                <x-text-input wire:model="slug" id="slug" type="text" :error="$errors->first('slug')" />
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
                @if ($this->post->coverImageUrl() && ! $removeCover && ! $cover)
                    <img src="{{ $this->post->coverImageUrl() }}" alt="" class="ms-mb-8" style="width:100%;max-width:360px;aspect-ratio:16/9;object-fit:cover;border-radius:8px">
                @endif
                @if ($cover)
                    <img src="{{ $cover->temporaryUrl() }}" alt="" class="ms-mb-8" style="width:100%;max-width:360px;aspect-ratio:16/9;object-fit:cover;border-radius:8px">
                @endif
                <input type="file" wire:model="cover" id="cover" accept="image/*" class="input">
                <div wire:loading wire:target="cover" class="text-muted ms-fs-12">Uploading…</div>
                <x-input-error :message="$errors->first('cover')" />
                @if ($this->post->cover_image)
                    <label class="ms-check-label-22">
                        <input wire:model="removeCover" type="checkbox" class="checkbox"> Remove cover image
                    </label>
                @endif
            </div>
        </div>

        <div class="ms-row-10">
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save">{{ __('posts.save_changes') }}</button>
            <a href="{{ route('admin.posts.index') }}" wire:navigate class="btn">{{ __('posts.cancel') }}</a>
        </div>
    </form>
</div>

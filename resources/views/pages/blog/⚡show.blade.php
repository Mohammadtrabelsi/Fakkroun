<?php

use App\Models\Post;
use App\Services\PostService;
use App\Services\StructuredDataService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Public detail page of a published post.
 */
new class extends Component
{
    #[Locked]
    public Post $post;

    public function mount(string $slug, PostService $posts): void
    {
        $this->post = $posts->findPublishedBySlug($slug) ?? abort(404);
    }

    public function render(): View
    {
        return $this->view()->layout('components.layouts.marketing', [
            'title' => $this->post->title.' — '.config('app.name'),
            'description' => $this->post->summary(160),
            'canonical' => $this->post->publicUrl(),
            'image' => $this->post->coverImageUrl(),
            'ogType' => 'article',
            'structuredData' => app(StructuredDataService::class)->forPost($this->post),
        ]);
    }
};
?>

<div>

    <article class="container post-page">
        <a href="{{ route('blog') }}" class="back-link">&larr; {{ __('blog.back') }}</a>

        <div class="post-meta">
            {{ $post->published_at?->translatedFormat('M j, Y') }}
            @if ($post->category) · {{ $post->category->name }} @endif
            @if ($post->author) · {{ $post->author->name }} @endif
        </div>

        <h1>{{ $post->title }}</h1>

        @if ($post->coverImageUrl())
            <img src="{{ $post->coverImageUrl() }}" alt="" class="post-cover">
        @endif

        <div class="post-body">
            {!! nl2br(e($post->body)) !!}
        </div>

        <x-blog.tag-list :tags="$post->tags" />
    </article>
</div>

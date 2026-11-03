<x-layouts.marketing
    :title="$post->title.' — '.config('app.name')"
    :description="$post->summary(160)"
    :canonical="$post->publicUrl()"
    :image="$post->coverImageUrl()"
    og-type="article"
    :structured-data="$structuredData"
>
    @push('styles')
        <style>
            .post-page { padding: 72px 48px 96px; max-width: 720px; }
            .post-page .post-meta { font-size: 13px; color: rgba(var(--ink), 0.6); margin-bottom: 16px; }
            .post-page h1 { font-family: var(--font-heading); font-weight: 600; font-size: 40px; line-height: 1.15; margin: 0 0 24px; }
            .post-page .post-cover { width: 100%; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 10px; margin-bottom: 32px; }
            .post-page .post-body { font-size: 17px; line-height: 1.75; }
            .post-page .blog-tags { display: flex; flex-wrap: wrap; gap: 6px; list-style: none; padding: 0; margin: 32px 0 0; }
            .post-page .blog-tags a { text-decoration: none; }
            .post-page .back-link { display: inline-block; margin-bottom: 32px; font-size: 13px; color: var(--color-accent); text-decoration: none; }
            @media (max-width: 900px) {
                .post-page { padding: 48px 24px 64px; }
                .post-page h1 { font-size: 30px; }
            }
        </style>
    @endpush

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
</x-layouts.marketing>

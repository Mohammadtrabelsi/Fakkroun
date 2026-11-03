<x-layouts.marketing
    :title="config('app.name').' — '.__('blog.title').($tag ? ' — #'.$tag->name : '')"
    :description="__('blog.description')"
    :canonical="$canonical"
    :structured-data="$structuredData"
>
    @push('styles')
        <style>
            .page-head { padding: 72px 48px 40px; }
            .post-list { display: flex; flex-direction: column; border-top: 1px solid var(--color-divider); }
            .post-row { display: grid; grid-template-columns: 160px 1fr; gap: 32px; padding: 28px 0; border-bottom: 1px solid var(--color-divider); }
            .post-row .post-date { font-size: 12px; letter-spacing: 0.06em; text-transform: uppercase; color: rgba(var(--ink), 0.55); }
            .post-row h3 { font-family: var(--font-heading); font-weight: 600; font-size: 20px; margin: 0 0 8px; }
            .post-row h3 a { color: inherit; text-decoration: none; }
            .post-row h3 a:hover { text-decoration: underline; }
            .post-row p { font-size: 14px; opacity: 0.75; margin: 0; max-width: 620px; }
            .post-row img { width: 100%; max-width: 620px; aspect-ratio: 16 / 9; object-fit: cover; border-radius: 8px; margin-bottom: 14px; }
            .blog-search { display: flex; gap: 8px; margin-top: 24px; max-width: 420px; }
            .blog-search .input { flex: 1; }
            @media (max-width: 900px) {
                .page-head { padding: 48px 24px 32px; }
                .post-row { grid-template-columns: 1fr; gap: 8px; }
            }
        </style>
    @endpush

    <div class="container page-head">
        <div class="tag tag-accent ms-mb-20">{{ __('blog.title') }}</div>
        <h1 class="ms-mw-560">{{ __('blog.heading', ['name' => config('app.name')]) }}</h1>
        <p class="text-muted ms-lead">{{ __('blog.description') }}</p>

        <form method="GET" action="{{ route('blog') }}" class="blog-search">
            <input type="search" name="q" value="{{ $search }}" class="input" placeholder="{{ __('blog.search_placeholder') }}" aria-label="{{ __('blog.search_placeholder') }}">
            @if ($tag)
                <input type="hidden" name="tag" value="{{ request('tag') }}">
            @endif
            <button type="submit" class="btn">{{ __('blog.search') }}</button>
        </form>

        <div class="blog-filter">
            @if ($tag)
                <span>{{ __('tags.filtered_by', ['tag' => $tag->name]) }}</span>
                <a href="{{ route('blog', array_filter(['q' => $search])) }}">{{ __('tags.clear_filter') }}</a>
            @endif
            @if (config('feed.enabled'))
                <a href="{{ route('feeds.blog') }}" class="text-muted">{{ __('blog.rss') }}</a>
            @endif
        </div>
    </div>

    <div class="container ms-pb-96">
        <div class="post-list">
            @forelse ($posts as $post)
                <article class="post-row">
                    <div class="post-date">
                        {{ $post->published_at?->translatedFormat('M Y') }}
                        @if ($post->category)
                            <div class="ms-mt-8">{{ $post->category->name }}</div>
                        @endif
                    </div>
                    <div>
                        @if ($post->coverImageUrl())
                            <a href="{{ route('blog.show', $post->slug) }}">
                                <img src="{{ $post->coverImageUrl() }}" alt="" loading="lazy">
                            </a>
                        @endif
                        <h3><a href="{{ route('blog.show', $post->slug) }}">{{ $post->title }}</a></h3>
                        <p>{{ $post->excerpt ?: Str::limit(strip_tags($post->body), 180) }}</p>
                        <x-blog.tag-list :tags="$post->tags" />
                    </div>
                </article>
            @empty
                <p class="text-muted ms-py-24">{{ __('blog.no_posts') }}</p>
            @endforelse
        </div>

        <div class="ms-mt-32">
            {{ $posts->links() }}
        </div>
    </div>
</x-layouts.marketing>

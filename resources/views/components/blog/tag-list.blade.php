{{-- Links each tag to its filtered /blog listing. Presentation only. --}}
@props(['tags'])

@if ($tags->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'blog-tags']) }} aria-label="{{ __('tags.label') }}">
        @foreach ($tags as $tag)
            @php($slug = $tag->getTranslation('slug', \App\Models\Tag::getLocale(), false))
            @if ($slug)
                <li><a href="{{ route('blog', ['tag' => $slug]) }}" class="tag tag-outline" rel="tag">#{{ $tag->name }}</a></li>
            @endif
        @endforeach
    </ul>
@endif

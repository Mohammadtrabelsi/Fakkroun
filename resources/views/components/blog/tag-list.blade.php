{{-- Links each tag to its filtered /blog listing. Presentation only. --}}
@props(['tags'])

@if ($tags->isNotEmpty())
    <ul {{ $attributes->merge(['class' => 'blog-tags']) }} aria-label="{{ __('tags.label') }}">
        @foreach ($tags as $tag)
            @if ($tag->localSlug())
                <li><a href="{{ route('blog', ['tag' => $tag->localSlug()]) }}" class="tag tag-outline" rel="tag">#{{ $tag->name }}</a></li>
            @endif
        @endforeach
    </ul>
@endif

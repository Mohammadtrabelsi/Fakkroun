{{-- A post as a printable document. Content is escaped; line breaks are kept. --}}
@extends('pdf.layout')

@section('title', $post->title)

@section('header', __('pdf.post_document'))

@section('content')
    <h1>{{ $post->title }}</h1>

    <table class="meta">
        <tr><th>{{ __('posts.status') }}</th><td>{{ \App\Models\Post::statuses()[$post->status] ?? $post->status }}</td></tr>
        @if ($post->published_at)
            <tr><th>{{ __('pdf.published') }}</th><td>{{ $post->published_at->translatedFormat('Y-m-d') }}</td></tr>
        @endif
        @if ($post->author)
            <tr><th>{{ __('posts.author') }}</th><td>{{ $post->author->name }}</td></tr>
        @endif
        @if ($post->category)
            <tr><th>{{ __('posts.category') }}</th><td>{{ $post->category->name }}</td></tr>
        @endif
        @if ($post->tags->isNotEmpty())
            <tr><th>{{ __('tags.label') }}</th><td>{{ $post->tags->pluck('name')->implode(', ') }}</td></tr>
        @endif
        @if ($post->isPublished())
            <tr><th>URL</th><td class="small">{{ $post->publicUrl() }}</td></tr>
        @endif
    </table>

    @if ($post->excerpt)
        <p class="muted"><em>{{ $post->excerpt }}</em></p>
    @endif

    <div>{!! nl2br(e($post->body)) !!}</div>
@endsection

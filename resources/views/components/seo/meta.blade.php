{{--
    Description, canonical URL and Open Graph / Twitter tags for public pages.
    All values are escaped; pass plain text.
--}}
@props([
    'title',
    'description' => null,
    'canonical' => null,
    'image' => null,
    'type' => 'website',
])

@php
    $description = $description ? \Illuminate\Support\Str::limit(trim(strip_tags($description)), 160) : null;
@endphp

@if ($description)
    <meta name="description" content="{{ $description }}">
@endif
@if ($canonical)
    <link rel="canonical" href="{{ $canonical }}">
@endif
<meta property="og:type" content="{{ $type }}">
<meta property="og:title" content="{{ $title }}">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:locale" content="{{ str_replace('-', '_', app()->getLocale()) }}">
@if ($canonical)
    <meta property="og:url" content="{{ $canonical }}">
@endif
@if ($description)
    <meta property="og:description" content="{{ $description }}">
@endif
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
<meta name="twitter:card" content="{{ $image ? 'summary_large_image' : 'summary' }}">

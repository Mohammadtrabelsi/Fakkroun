@props(['user', 'size' => null])
{{-- Profile picture when one is uploaded, otherwise the user's initials. --}}
@php($style = $size ? "width: {$size}px; height: {$size}px; font-size: ".max(12, (int) round($size * 0.38)).'px;' : null)
@if ($url = $user->avatarUrl())
    <img src="{{ $url }}" alt="" {{ $attributes->merge(['class' => 'avatar avatar-img', 'style' => $style]) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar', 'style' => $style]) }}>{{ $user->initials() }}</span>
@endif

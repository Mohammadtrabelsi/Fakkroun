@props(['user', 'size' => null])
{{-- Profile picture when one is uploaded, otherwise the user's initials. A given size is passed as a CSS variable. --}}
@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="" {{ $attributes->merge(['class' => 'avatar avatar-img'.($size ? ' avatar-sized' : ''), 'style' => $size ? '--avatar-size: '.$size.'px' : null]) }}>
@else
    <span {{ $attributes->merge(['class' => 'avatar'.($size ? ' avatar-sized' : ''), 'style' => $size ? '--avatar-size: '.$size.'px' : null]) }}>{{ $user->initials() }}</span>
@endif

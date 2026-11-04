@props(['size' => 48, 'details' => false, 'dark' => false, 'label' => null])
{{--
    Fakkroun the turtle, drawn top-down with a front-facing head: TWO eyes and a
    smile (client requirement). `details` adds the thought bubbles, eye shines,
    cheeks, tail and shell rim used by the big hero mascot. `dark` uses the
    lighter shell for dark backgrounds.
--}}
@php($shell = $dark ? '#13857E' : '#0B6E69')
<svg width="{{ $size }}" height="{{ (int) round($size * 110 / 140) }}" viewBox="0 0 140 110"
     @if ($label) role="img" aria-label="{{ $label }}" @else aria-hidden="true" @endif
     {{ $attributes }}>
    <ellipse cx="38" cy="84" rx="14" ry="9" fill="#7DB57A" transform="rotate(-25 38 84)"/>
    <ellipse cx="92" cy="86" rx="14" ry="9" fill="#7DB57A" transform="rotate(25 92 86)"/>
    <ellipse cx="34" cy="38" rx="14" ry="8" fill="#7DB57A" transform="rotate(30 34 38)"/>
    <ellipse cx="96" cy="36" rx="14" ry="8" fill="#7DB57A" transform="rotate(-30 96 36)"/>
    @if ($details)
        <path d="M22 64 l-10 4 l10 3 z" fill="#7DB57A"/>
    @endif
    <ellipse cx="64" cy="60" rx="42" ry="34" fill="{{ $shell }}"/>
    @if ($details)
        <ellipse cx="64" cy="60" rx="42" ry="34" fill="none" stroke="#084F4B" stroke-width="3"/>
    @endif
    <polygon points="77,60 70.5,71.3 57.5,71.3 51,60 57.5,48.7 70.5,48.7" fill="#F2A33A"/>
    <g stroke="#F5EFE3" stroke-width="3" stroke-linecap="round" opacity="0.8">
        <line x1="77" y1="60" x2="104" y2="60"/><line x1="70.5" y1="71.3" x2="82" y2="91"/>
        <line x1="57.5" y1="71.3" x2="46" y2="91"/><line x1="51" y1="60" x2="24" y2="60"/>
        <line x1="57.5" y1="48.7" x2="46" y2="29"/><line x1="70.5" y1="48.7" x2="82" y2="29"/>
    </g>
    <circle cx="116" cy="58" r="19" fill="#7DB57A"/>
    <circle cx="109" cy="54" r="5" fill="#fff"/><circle cx="123" cy="54" r="5" fill="#fff"/>
    <circle cx="109.5" cy="55" r="2.8" fill="#10262E"/><circle cx="123.5" cy="55" r="2.8" fill="#10262E"/>
    @if ($details)
        <circle cx="110.6" cy="53.8" r="1" fill="#fff"/><circle cx="124.6" cy="53.8" r="1" fill="#fff"/>
        <circle cx="104" cy="62" r="2.6" fill="#F2A33A" opacity="0.6"/><circle cx="128" cy="62" r="2.6" fill="#F2A33A" opacity="0.6"/>
    @endif
    <path d="M109 64 q7 6 14 0" stroke="#10262E" stroke-width="2.5" fill="none" stroke-linecap="round"/>
    @if ($details)
        <circle cx="128" cy="30" r="3" fill="#F2A33A"/><circle cx="134" cy="20" r="4.5" fill="#F2A33A"/>
    @endif
</svg>

{{-- Fakkroun turtle mark: a thinking turtle (shell, head, idea spark). Strokes use currentColor so the mark follows the surrounding text colour. --}}
<svg {{ $attributes->merge(['class' => 'brand-mark']) }} viewBox="0 0 112 104" aria-hidden="true" focusable="false">
    {{-- shell --}}
    <path d="M12 70 C12 40 30 26 48 26 C66 26 84 40 84 70 Z" fill="none" stroke="currentColor" stroke-width="9" stroke-linejoin="round"/>
    {{-- shell pattern --}}
    <path d="M48 38 L58 44 L58 56 L48 62 L38 56 L38 44 Z" fill="none" stroke="var(--color-teal-light)" stroke-width="6" stroke-linejoin="round"/>
    {{-- legs --}}
    <path d="M26 72 L22 88 M70 72 L74 88" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"/>
    {{-- neck and head --}}
    <path d="M82 62 L88 60" fill="none" stroke="currentColor" stroke-width="9" stroke-linecap="round"/>
    <circle cx="96" cy="58" r="9" fill="none" stroke="currentColor" stroke-width="7"/>
    <circle cx="98" cy="56" r="2" fill="currentColor"/>
    {{-- idea spark --}}
    <path d="M96 2 L99 11 L108 14 L99 17 L96 26 L93 17 L84 14 L93 11 Z" fill="var(--color-spark)"/>
</svg>

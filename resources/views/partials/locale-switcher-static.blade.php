
{{-- JS-free language switcher for the front office (marketing) layout, which
     does not load Alpine. Uses a native <details> disclosure so it works
     without any JavaScript while reusing the shared icon-btn / menu-pop styles. --}}
@if (count(config('app.available_locales', [])) > 1)
    <details class="locale-switcher">
        <summary class="icon-btn" title="{{ __('actions.change_language') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"></path></svg>
            <span class="ms-uppercase-12">{{ app()->getLocale() }}</span>
        </summary>

        <div class="menu-pop">
            @foreach (config('app.available_locales', []) as $code => $meta)
                <a
                    href="{{ route('locale.switch', $code) }}"
                    class="{{ app()->getLocale() === $code ? 'active' : '' }}"
                >
                    {{ $meta['label'] ?? strtoupper($code) }}
                </a>
            @endforeach
        </div>
    </details>
@endif

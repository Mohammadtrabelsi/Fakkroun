
@if (count(config('app.available_locales', [])) > 1)
    <div class="ms-relative" data-dropdown>
        <button type="button" data-dropdown-toggle class="icon-btn" title="{{ __('actions.change_language') }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="9"></circle><path d="M3 12h18M12 3a15 15 0 010 18M12 3a15 15 0 000 18"></path></svg>
            <span class="ms-uppercase-12">{{ app()->getLocale() }}</span>
        </button>

        <div data-dropdown-menu class="menu-pop">
            @foreach (config('app.available_locales', []) as $code => $meta)
                <a
                    href="{{ route('locale.switch', $code) }}"
                    class="{{ app()->getLocale() === $code ? 'active' : '' }}"
                >
                    {{ $meta['label'] ?? strtoupper($code) }}
                </a>
            @endforeach
        </div>
    </div>
@endif

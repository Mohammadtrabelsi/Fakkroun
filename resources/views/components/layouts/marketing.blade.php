<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        @php
            $pageTitle = $title ?? config('app.name').' — '.__('fakkroun.tagline');
        @endphp
        <title>{{ $pageTitle }}</title>

        <x-seo.meta
            :title="$pageTitle"
            :description="$description ?? null"
            :canonical="$canonical ?? null"
            :image="$image ?? null"
            :type="$ogType ?? 'website'"
        />
        @if (config('feed.enabled'))
            @include('feed::links')
        @endif
        <x-seo.structured-data :graph="$structuredData ?? null" />

        @include('partials.favicons')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="antialiased">
        <header class="nav">
            <a href="{{ url('/') }}" class="brand">
                <x-brand-mark />
                <span class="brand-name">{{ config('app.name') }}</span>
            </a>
            <button
                type="button"
                class="icon-btn nav-toggle"
                data-nav-toggle
                aria-controls="site-nav"
                aria-expanded="false"
                aria-label="{{ __('marketing.menu') }}"
            >
                <svg class="nav-icon-open" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"></path></svg>
                <svg class="nav-icon-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"></path></svg>
            </button>
            <div class="nav-panel" id="site-nav" data-nav-panel>
                <nav class="nav-links" aria-label="{{ __('marketing.menu') }}">
                    @foreach (config('navigation.marketing', []) as $item)
                        @php
                            $isRoute = isset($item['route']);
                            $current = $isRoute && request()->routeIs($item['route'], $item['route'].'.*');
                        @endphp
                        <a
                            href="{{ $isRoute ? route($item['route']) : url($item['url']) }}"
                            class="link"
                            @if ($current) aria-current="page" @endif
                        >{{ __($item['label']) }}</a>
                    @endforeach
                </nav>
                <div class="nav-actions">
                    @if (auth()->user()?->isGuest() === false)
                        <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('marketing.dashboard') }}</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-ghost">{{ __('marketing.sign_in') }}</a>
                        <a href="{{ route('register') }}" class="btn btn-primary">{{ __('marketing.get_started') }}</a>
                    @endif
                </div>
            </div>
            @include('partials.locale-switcher-static')
        </header>

        <main>
            {{ $slot }}
        </main>

        <footer class="site-footer pt-5 mt-5">
            <div class="container footer-grid">
                <div class="footer-brand">
                    <a href="{{ url('/') }}" class="brand">
                        <x-brand-mark />
                        <span class="brand-name">{{ config('app.name') }}</span>
                    </a>
                    <p class="text-muted footer-brand-copy">{{ __('marketing.tagline') }}</p>
                    <a href="{{ route('register') }}" class="btn btn-primary mb-4">{{ __('marketing.get_started') }}</a>
                </div>
                <nav aria-label="Product">
                    <div class="footer-heading">{{ __('marketing.product') }}</div>
                    <div class="footer-links">
                        <a href="{{ url('/') }}#features">{{ __('marketing.features') }}</a>
                        <a href="{{ route('docs') }}">{{ __('marketing.docs') }}</a>
                    </div>
                </nav>
                <nav aria-label="Company">
                    <div class="footer-heading">{{ __('marketing.company') }}</div>
                    <div class="footer-links">
                        <a href="{{ route('about') }}">{{ __('marketing.about') }}</a>
                        <a href="{{ route('blog') }}">{{ __('marketing.blog') }}</a>
                        <a href="{{ config('app.github_url') }}" target="_blank" rel="noopener">{{ __('marketing.github') }}</a>
                        <a href="https://opensource.org/licenses/MIT" target="_blank" rel="noopener">{{ __('marketing.license') }}</a>
                    </div>
                </nav>
            </div>
            <div class="container footer-bottom">
                <div class="text-muted ms-fs-12">{{ __('home.footer.made_in', ['year' => config('fakkroun.brand.copyright_year')]) }}</div>
            </div>
        </footer>
    </body>
</html>

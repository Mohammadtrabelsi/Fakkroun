<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        {{-- Blocking on purpose: sets the theme before first paint (resources/js/theme.js). --}}
        @themeScript

        @include('partials.favicons')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="antialiased">
        <div class="ms-corner-actions">
            @include('partials.locale-switcher')
            @include('partials.theme-toggle')
        </div>
        <div class="auth-split">
            <div class="auth-hero">
                <div class="auth-hero-body">
                    <span class="brand-mark ms-brand-invert">{{ Str::substr(config('app.name', 'M'), 0, 1) }}</span>
                    <p class="auth-hero-tag">{{ __('marketing.hero_tag') }}</p>
                    <h2>{{ __('app.welcome') }} {{ config('app.name') }}.</h2>
                    <p>{{ __('marketing.hero_description', ['name' => config('app.name')]) }}</p>

                    <h3 class="auth-hero-subtitle">{{ __('marketing.auth_highlights_title') }}</h3>
                    <ul class="auth-hero-list">
                        @foreach (['roles', 'activity', 'security', 'i18n'] as $highlight)
                            <li>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 12.5l4.5 4.5L19 7.5"></path></svg>
                                <span>{{ __('marketing.auth_highlight_'.$highlight) }}</span>
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ config('app.github_url') }}" target="_blank" rel="noopener" class="auth-hero-link">
                        {{ __('marketing.view_on_github') }}
                    </a>
                </div>
            </div>

            <div class="auth-form-side">
                <div class="ms-mw-360-full">
                    <a href="{{ url('/') }}" wire:navigate class="brand ms-mb-36">
                        <x-brand-mark />
                        <span class="brand-name">{{ config('app.name') }}</span>
                    </a>

                    {{ $slot }}
                </div>
            </div>
        </div>

        @livewireScripts
    </body>
</html>

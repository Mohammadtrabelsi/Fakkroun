@props(['title' => null, 'description' => null, 'canonical' => null, 'structuredData' => null])
{{-- Public Fakkroun layout (fakkroun-design/design/Main.dc.html header + footer). --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>{{ $title ?? config('app.name').' — '.__('fakkroun.tagline') }}</title>
        <x-seo.meta :title="($title ?? config('app.name').' — '.__('fakkroun.tagline'))" :description="$description ?? __('marketing.tagline')" :canonical="$canonical" :image="null" type="website" />
        <x-seo.structured-data :graph="$structuredData" />
        @include('partials.favicons')
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@500;700;800&family=Readex+Pro:wght@400;500;600&display=swap" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @stack('styles')
    </head>
    <body class="fk-page antialiased">
        <a href="#main" class="sr-only focus:not-sr-only focus:absolute focus:start-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-paper focus:px-4 focus:py-2">{{ __('home.nav.main') }}</a>

        <header class="border-b border-sand-300 bg-sand">
            <div class="fk-container flex flex-wrap items-center gap-x-3 gap-y-3 py-2.5 max-sm:px-4 sm:py-3.5 lg:gap-x-8">
                <x-fakkroun.logo />

                {{-- Small screens: Play now + menu toggle stay visible, the rest folds into the panel below. --}}
                <div class="ms-auto flex items-center gap-2 lg:hidden">
                    <a href="{{ $playUrl }}" class="fk-btn min-h-11 bg-sea px-3 text-[15px] whitespace-nowrap text-paper hover:bg-sea-dark hover:text-paper sm:px-4 sm:text-base">{{ __('home.nav.play_now') }}</a>
                    <button type="button" data-nav-toggle aria-controls="fk-site-nav" aria-expanded="false" aria-label="{{ __('home.nav.menu') }}"
                            class="group flex size-11 items-center justify-center rounded-xl border-2 border-sand-300 bg-paper text-ink hover:bg-sand-200">
                        <svg class="group-aria-expanded:hidden" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                        <svg class="hidden group-aria-expanded:block" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>
                    </button>
                </div>

                <div id="fk-site-nav" data-nav-panel
                     class="hidden w-full flex-col gap-4 border-t border-sand-300 pt-3 pb-2 [&.is-open]:flex lg:flex lg:w-auto lg:flex-1 lg:flex-row lg:flex-wrap lg:items-center lg:gap-x-8 lg:gap-y-3 lg:border-0 lg:p-0">
                    <nav aria-label="{{ __('home.nav.main') }}" class="flex flex-col gap-0.5 lg:flex-row lg:flex-wrap">
                        @foreach ([
                            [__('home.nav.play'), $playUrl],
                            [__('home.nav.categories'), url('/#categories')],
                            [__('home.nav.learn'), url('/#learn')],
                            [__('home.nav.community'), route('questions.submit')],
                        ] as [$label, $href])
                            <a href="{{ $href }}" class="flex min-h-11 items-center rounded-[10px] px-3.5 font-medium text-ink no-underline hover:bg-sand-200">{{ $label }}</a>
                        @endforeach
                        @if (Route::has('leaderboard'))
                            <a href="{{ route('leaderboard') }}" class="flex min-h-11 items-center rounded-[10px] px-3.5 font-medium text-ink no-underline hover:bg-sand-200">{{ __('home.nav.leaderboard') }}</a>
                        @endif
                    </nav>

                    <div class="flex flex-wrap items-center justify-between gap-2.5 border-t border-sand-300 pt-3 lg:ms-auto lg:justify-start lg:border-0 lg:pt-0">
                        <div role="group" aria-label="{{ __('home.nav.language') }}" class="flex gap-0.5 rounded-xl bg-sand-200 p-[3px]">
                            @foreach (['en' => 'EN', 'fr' => 'FR', 'ar' => 'عربي'] as $code => $label)
                                @if (array_key_exists($code, config('app.available_locales')))
                                    <a href="{{ route('locale.switch', $code) }}"
                                       @if ($code === 'ar') lang="ar" @endif
                                       aria-current="{{ app()->getLocale() === $code ? 'true' : 'false' }}"
                                       class="flex min-h-[38px] min-w-11 items-center justify-center rounded-[9px] px-2 text-sm font-semibold no-underline {{ app()->getLocale() === $code ? 'bg-paper text-ink' : 'text-ink-soft' }}">{{ $label }}</a>
                                @endif
                            @endforeach
                        </div>
                        @if (auth()->user()?->isGuest())
                            <span class="flex min-h-11 min-w-0 items-center px-3.5 font-semibold text-ink"><span class="truncate">{{ auth()->user()->name }}</span></span>
                        @elseif (auth()->check())
                            <a href="{{ route('dashboard') }}" class="flex min-h-11 min-w-0 items-center px-3.5 font-semibold text-ink no-underline"><span class="truncate">{{ auth()->user()->name }}</span></a>
                        @else
                            <a href="{{ route('login') }}" class="flex min-h-11 items-center px-3.5 font-semibold text-ink no-underline">{{ __('home.nav.sign_in') }}</a>
                        @endif
                        {{-- Already shown next to the menu toggle on small screens. --}}
                        <div class="hidden lg:block">
                            <a href="{{ $playUrl }}" class="fk-btn bg-sea text-paper hover:bg-sea-dark hover:text-paper">{{ __('home.nav.play_now') }}</a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <main id="main">
            {{ $slot }}
        </main>

        <footer class="bg-ink text-[#C9D3D0]">
            <div class="fk-container flex flex-col gap-10 pt-14 pb-8">
                <div class="grid gap-8 [grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))]">
                    <div class="flex flex-col gap-2.5">
                        <span class="font-display text-[28px] font-extrabold text-sand">Fakkroun <span lang="ar" class="text-sun">فكّرون</span></span>
                        <span class="leading-relaxed">{{ __('home.footer.tagline') }}</span>
                    </div>
                    @foreach ([
                        [__('home.footer.play'), [[__('home.footer.create'), $playUrl], [__('home.footer.join'), route('rooms.join')]]],
                        [__('home.footer.contribute'), [[__('home.footer.submit'), route('questions.submit')], [__('home.footer.schools'), url('/#partners')], [__('home.footer.partners'), url('/#partners')]]],
                        [__('home.footer.about'), [[__('home.footer.mission'), route('about')], [__('home.footer.terms'), route('terms')]]],
                    ] as [$heading, $links])
                        <nav aria-label="{{ $heading }}" class="flex flex-col gap-1">
                            <b class="mb-1.5 text-sand">{{ $heading }}</b>
                            @foreach ($links as [$label, $href])
                                <a href="{{ $href }}" @if (url()->current() === $href) aria-current="page" @endif class="flex min-h-8 items-center text-[#C9D3D0] hover:text-sand">{{ $label }}</a>
                            @endforeach
                        </nav>
                    @endforeach
                </div>
                <div class="flex flex-wrap justify-between gap-3 border-t border-[#284750] pt-5 text-sm">
                    <span>{{ __('home.footer.made_in', ['year' => config('fakkroun.brand.copyright_year')]) }}</span>
                    <span>English · Français · <span lang="ar">العربية</span></span>
                </div>
            </div>
        </footer>
        @stack('scripts')
    </body>
</html>

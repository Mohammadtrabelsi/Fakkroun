<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}"
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>{{ $title ?? config('app.name') }}</title>

        @include('partials.theme-script')

        @include('partials.favicons')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        @livewireStyles
    </head>
    <body class="antialiased">
        <div class="shell" data-sidebar>
            <div
                class="shell-overlay ms-hidden"
                data-sidebar-overlay
                data-sidebar-close
            ></div>

            <aside class="shell-sidebar">
                <a href="{{ auth()->user()?->can('view admin dashboard') ? route('admin.dashboard') : route('dashboard') }}" wire:navigate class="brand ms-pad-x20-b22">
                    <x-brand-mark />
                    <span class="brand-name">{{ config('app.name') }}</span>
                </a>

                <nav class="side-nav">
                    @if (auth()->user()?->canAccessBackoffice())
                        @can('view admin dashboard')
                            <a href="{{ route('admin.dashboard') }}" wire:navigate class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="3"></rect><path d="M8 17v-5M12 17V8M16 17v-3"></path></svg>
                                {{ __('app.dashboard') }}
                            </a>
                        @endcan

                        @can('manage questions')
                            <a href="{{ route('admin.questions.index') }}" wire:navigate class="{{ request()->routeIs('admin.questions.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"></circle><path d="M9.5 9a2.5 2.5 0 1 1 3.5 2.3c-.6.3-1 .9-1 1.6V14M12 17.5v.01"></path></svg>
                                {{ __('questions.admin.title') }}
                            </a>
                        @endcan

                        @can('manage questions')
                            <a href="{{ route('admin.ai') }}" wire:navigate class="{{ request()->routeIs('admin.ai') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l1.8 4.7L18.5 9.5l-4.7 1.8L12 16l-1.8-4.7L5.5 9.5l4.7-1.8z"></path><path d="M19 15l.8 2.2L22 18l-2.2.8L19 21l-.8-2.2L16 18l2.2-.8z"></path></svg>
                                {{ __('ai.title') }}
                            </a>
                        @endcan

                        @can('moderate questions')
                            <a href="{{ route('admin.moderation') }}" wire:navigate class="{{ request()->routeIs('admin.moderation') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 11l3 3 8-8"></path><path d="M20 12v7a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h9"></path></svg>
                                {{ __('questions.moderation.title') }}
                            </a>
                        @endcan

                        @can('manage users')
                            <a href="{{ route('admin.users.index') }}" wire:navigate class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="9" cy="8" r="3.5"></circle><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6"></path><path d="M16 4.5a3.5 3.5 0 0 1 0 7M18 14.2c2 .7 3.5 2.6 3.5 5.8"></path></svg>
                                {{ __('app.users') }}
                            </a>
                        @endcan

                        @can('view activity log')
                            <a href="{{ route('admin.activity-log') }}" wire:navigate class="{{ request()->routeIs('admin.activity-log') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7"></path><path d="M3 4v5h5"></path><path d="M12 8v4l3 2"></path></svg>
                                {{ __('app.activity_log') }}
                            </a>
                        @endcan

                        @can('manage settings')
                            <a href="{{ route('admin.settings') }}" wire:navigate class="{{ request()->routeIs('admin.settings') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="12" r="3"></circle><path d="M12 2v3M12 19v3M4.2 4.2l2.1 2.1M17.7 17.7l2.1 2.1M2 12h3M19 12h3M4.2 19.8l2.1-2.1M17.7 6.3l2.1-2.1"></path></svg>
                                {{ __('app.settings') }}
                            </a>
                        @endcan

                        @can('manage platform settings')
                            <a href="{{ route('admin.game-settings') }}" wire:navigate class="{{ request()->routeIs('admin.game-settings') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="7" width="20" height="12" rx="4"></rect><path d="M7 11v4M5 13h4"></path><circle cx="16" cy="12" r="1"></circle><circle cx="18" cy="14.5" r="1"></circle></svg>
                                {{ __('fakkroun.game_settings') }}
                            </a>
                        @endcan

                        @can('manage advertising')
                            <a href="{{ route('admin.partners') }}" wire:navigate class="{{ request()->routeIs('admin.partners') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 11l3 3 6-6"></path><rect x="3" y="4" width="18" height="16" rx="3"></rect></svg>
                                {{ __('partners.title') }}
                            </a>
                        @endcan

                        @can('manage advertising')
                            <a href="{{ route('admin.advertising') }}" wire:navigate class="{{ request()->routeIs('admin.advertising') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"></path><path d="M15 9a3 3 0 0 1 0 6"></path><path d="M18 6a7 7 0 0 1 0 12"></path></svg>
                                {{ __('advertising.admin_title') }}
                            </a>
                        @endcan

                        @can('view admin dashboard')
                            <a href="{{ route('admin.analytics') }}" wire:navigate class="{{ request()->routeIs('admin.analytics') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 20V10"></path><path d="M10 20V4"></path><path d="M16 20v-7"></path><path d="M22 20H2"></path></svg>
                                {{ __('analytics.title') }}
                            </a>
                        @endcan

                        @can('manage moderators')
                            <a href="{{ route('admin.moderators') }}" wire:navigate class="{{ request()->routeIs('admin.moderators') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6z"></path><path d="M9 12l2 2 4-4"></path></svg>
                                {{ __('fakkroun.moderators') }}
                            </a>
                        @endcan

                        @can('view system health')
                            <a href="{{ route('admin.health') }}" wire:navigate class="{{ request()->routeIs('admin.health') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h4l2-5 4 10 2-5h6"></path></svg>
                                {{ __('app.health') }}
                            </a>
                        @endcan

                        @can('manage backups')
                            <a href="{{ route('admin.backups') }}" wire:navigate class="{{ request()->routeIs('admin.backups') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><ellipse cx="12" cy="6" rx="8" ry="3"></ellipse><path d="M4 6v6c0 1.7 3.6 3 8 3s8-1.3 8-3V6M4 12v6c0 1.7 3.6 3 8 3s8-1.3 8-3v-6"></path></svg>
                                {{ __('app.backups') }}
                            </a>
                        @endcan

                        <a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11l9-7 9 7v9a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"></path></svg>
                            {{ __('app.my_dashboard') }}
                        </a>
                        @can('play games')
                            <a href="{{ route('rooms.create') }}" wire:navigate class="{{ request()->routeIs('rooms.create') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14l11-7z"></path></svg>
                                {{ __('play.nav.new_room') }}
                            </a>
                            <a href="{{ route('rooms.join') }}" wire:navigate class="{{ request()->routeIs('rooms.join') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"></path></svg>
                                {{ __('play.nav.join_room') }}
                            </a>
                        @endcan
                        @can('submit questions')
                            <a href="{{ route('questions.mine') }}" wire:navigate class="{{ request()->routeIs('questions.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"></path></svg>
                                {{ __('questions.mine.title') }}
                            </a>
                        @endcan
                        @can('manage own campaigns')
                            <a href="{{ route('advertiser.dashboard') }}" wire:navigate class="{{ request()->routeIs('advertiser.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 11v2a1 1 0 0 0 1 1h2l5 4V6L6 10H4a1 1 0 0 0-1 1z"></path><path d="M15 9a3 3 0 0 1 0 6"></path></svg>
                                {{ __('advertising.nav') }}
                            </a>
                        @endcan


                        @canany(['manage posts', 'manage categories', 'manage tags'])
                            <div class="side-nav-label">{{ __('app.marketing_site') }}</div>
                        @endcanany

                        @can('manage posts')
                            <a href="{{ route('admin.posts.index') }}" wire:navigate class="{{ request()->routeIs('admin.posts.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="4" y="3" width="16" height="18" rx="2"></rect><path d="M8 8h8M8 12h8M8 16h5"></path></svg>
                                {{ __('app.posts') }}
                            </a>
                        @endcan

                        @can('manage categories')
                            <a href="{{ route('admin.categories.index') }}" wire:navigate class="{{ request()->routeIs('admin.categories.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path></svg>
                                {{ __('app.categories') }}
                            </a>
                        @endcan

                        @can('manage tags')
                            <a href="{{ route('admin.tags.index') }}" wire:navigate class="{{ request()->routeIs('admin.tags.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12l-8 8-9-9V3h8z"></path><circle cx="7.5" cy="7.5" r="1.5"></circle></svg>
                                {{ __('app.tags') }}
                            </a>
                        @endcan

                        <a href="{{ url('/') }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M6 3h8l5 5v13H6z"></path><path d="M14 3v5h5"></path></svg>
                            {{ __('app.view_site') }}
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="3"></rect><path d="M8 17v-5M12 17V8M16 17v-3"></path></svg>
                            {{ __('app.my_space') }}
                        </a>
                        @can('play games')
                            <a href="{{ route('rooms.create') }}" wire:navigate class="{{ request()->routeIs('rooms.create') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M8 5v14l11-7z"></path></svg>
                                {{ __('play.nav.new_room') }}
                            </a>
                            <a href="{{ route('rooms.join') }}" wire:navigate class="{{ request()->routeIs('rooms.join') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4M10 17l5-5-5-5M15 12H3"></path></svg>
                                {{ __('play.nav.join_room') }}
                            </a>
                        @endcan
                        @can('submit questions')
                            <a href="{{ route('questions.mine') }}" wire:navigate class="{{ request()->routeIs('questions.*') ? 'active' : '' }}">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"></path></svg>
                                {{ __('questions.mine.title') }}
                            </a>
                        @endcan

                        <a href="{{ route('profile.edit') }}" wire:navigate class="{{ request()->routeIs('profile.edit') ? 'active' : '' }}">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><circle cx="12" cy="8" r="3.5"></circle><path d="M5 20c0-3.3 3-5 7-5s7 1.7 7 5"></path></svg>
                            {{ __('app.profile') }}
                        </a>
                    @endif
                </nav>

                <a href="{{ route('profile.edit') }}" wire:navigate class="side-user">
                    <x-user-avatar :user="auth()->user()" />
                    <span class="ms-minw-0 ms-flex-1">
                        <span class="text-muted ms-fs-12 ms-block">{{ __('app.welcome_back') }}</span>
                        <span class="ms-ellipsis-strong ms-block">{{ auth()->user()->name }}</span>
                    </span>
                    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M9 6l6 6-6 6"></path></svg>
                </a>
            </aside>

            <div class="shell-main">
                <div class="shell-topbar">
                    <button type="button" class="topbar-burger" data-sidebar-open aria-label="Open menu">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75"><path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>

                    <div class="ms-flex-1"></div>

                    @if (auth()->user()?->canAccessBackoffice())
                        @php($unreadCount = auth()->user()->unreadNotifications()->count())
                        <a
                            href="{{ route('admin.notifications.index') }}"
                            wire:navigate
                            class="ms-iconbtn-plain"
                            aria-label="{{ __('notifications.bell_label') }}"
                            title="{{ __('notifications.bell_label') }}"
                        >
                            <span class="ms-row-12-rel">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                                @if ($unreadCount > 0)
                                    <span class="btn-badge">{{ min($unreadCount, 99) }}</span>
                                @endif
                            </span>
                        </a>
                    @endif

                    @unless (auth()->user()?->canAccessBackoffice())
                        @php($unreadCount = auth()->user()->unreadNotifications()->count())
                        <a href="{{ route('notifications') }}" wire:navigate class="ms-iconbtn-plain" aria-label="{{ __('play.inbox.title') }}" title="{{ __('play.inbox.title') }}">
                            <span class="ms-row-12-rel">
                                <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"></path><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"></path></svg>
                                @if ($unreadCount > 0)<span class="btn-badge">{{ min($unreadCount, 99) }}</span>@endif
                            </span>
                        </a>
                    @endunless

                    @include('partials.locale-switcher')
                    @include('partials.theme-toggle')

                    <div class="ms-row-12-rel" data-dropdown>
                        <button type="button" data-dropdown-toggle class="ms-iconbtn-plain">
                            <x-user-avatar :user="auth()->user()" />
                            <span class="hidden sm:inline ms-strong-13">{{ auth()->user()->name }}</span>
                            @if (auth()->user()->isAdmin())
                                <span class="tag tag-accent hidden sm:inline-flex">{{ __('app.admin') }}</span>
                            @endif
                        </button>

                        <div
                            data-dropdown-menu
                            class="blueprint ms-menu-abs"
                        >
                            <div class="text-muted ms-menu-head">
                                {{ auth()->user()->email }}
                            </div>
                            <a href="{{ route('profile.edit') }}" wire:navigate class="ms-menu-link">
                                <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="8" r="3.2"></circle><path d="M5 20c0-3.3 3-5 7-5s7 1.7 7 5"></path></svg>
                                {{ __('app.profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="ms-border-top">
                                @csrf
                                <button type="submit" class="ms-menu-btn-danger">
                                    <svg viewBox="0 0 24 24" width="15" height="15" fill="none" stroke="currentColor" stroke-width="1.5"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                    {{ __('app.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <main class="shell-content">
                    {{ $slot }}
                </main>

                <footer class="shell-footer">
                    <div class="text-muted ms-fs-12">© {{ date('Y') }} {{ config('app.name') }}.</div>
                    <span class="footer-status">{{ __('marketing.in_memory') }}</span>
                </footer>
            </div>
        </div>

        <x-toast-hub />

        @livewireScripts
    </body>
</html>

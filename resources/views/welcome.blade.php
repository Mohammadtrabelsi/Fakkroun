<x-layouts.marketing :structured-data="$structuredData ?? null" :canonical="route('home')">
    @push('styles')
        <style>
            /* Navy hero with a light diagonal panel, as in the brand visual. */
            .hero-band { position: relative; overflow: hidden; background: var(--color-navy); color: #ffffff; }
            .hero-band::before { content: ""; position: absolute; inset: 0; background: #e9eef4; clip-path: polygon(64% 0, 100% 0, 100% 100%, 78% 100%); }
            [dir="rtl"] .hero-band::before { clip-path: polygon(0 0, 36% 0, 22% 100%, 0 100%); }
            html.dark .hero-band { background: #1d3a5c; }
            html.dark .hero-band::before { background: #24303d; }
            .hero-band .tag { background: rgba(255, 255, 255, 0.12); color: #ffffff; }
            .hero-band .tag::before { content: ""; width: 8px; height: 8px; border-radius: 50%; background: var(--color-orange); }
            .hero-band h1 { color: #ffffff; }
            .hero-band .hero-lead { color: #d3dde8; font-size: 17px; max-width: 460px; }
            .hero-band .btn:not(.btn-primary) { background: transparent; color: #ffffff; border-color: rgba(255, 255, 255, 0.4); }
            .hero-band .btn:not(.btn-primary):hover { background: rgba(255, 255, 255, 0.1); }
            .hero-band figure.blueprint { background: var(--color-surface); border-color: transparent; box-shadow: 0 30px 70px rgba(10, 25, 45, 0.35); }
            .hero-band .pv-caption { color: var(--color-text); }
            .hero-grid { position: relative; display: grid; grid-template-columns: 1fr 1fr; gap: 56px; align-items: center; padding: 88px 48px; }
            .feature-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 18px; }
            .feature-cell { background: var(--color-surface); border: 1px solid var(--color-divider); border-radius: 8px; padding: 26px; }
            .feature-cell .card-kicker { color: var(--color-accent); font-weight: 600; }
            .stat-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 32px; text-align: center; }

            /* Static dashboard mock: mirrors the admin shell (sidebar + topbar + cards) with sample data. */
            figure.blueprint { margin: 0; }
            .hero-grid > * { min-width: 0; }
            .pv { display: grid; grid-template-columns: 30% minmax(0, 1fr); aspect-ratio: 4 / 3; overflow: hidden; border-radius: 6px; background: var(--color-bg); border: 1px solid var(--color-divider); font-size: 11px; }
            .pv-side { display: flex; flex-direction: column; gap: 8px; padding: 14px 12px; background: var(--color-sidebar); }
            .pv-side .brand-mark { margin: 0 auto 8px; color: #ffffff; }
            .pv-item { display: block; height: 18px; border-radius: 4px; background: rgba(255, 255, 255, 0.08); }
            .pv-item.is-active { background: var(--color-accent); }
            .pv-main { display: flex; flex-direction: column; gap: 12px; padding: 14px 16px; min-width: 0; }
            .pv-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .pv-top .avatar { width: 22px; height: 22px; font-size: 9px; }
            .pv-bar { display: block; height: 6px; background: rgba(var(--ink), 0.14); }
            .pv-stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
            .pv-stat { display: flex; flex-direction: column; gap: 8px; padding: 10px; border-radius: 6px; background: var(--color-surface); border: 1px solid var(--color-divider); }
            .pv-value { font-family: var(--font-heading); font-weight: 600; font-size: 20px; line-height: 1; }
            .pv-chart { flex: 1; min-height: 0; display: flex; align-items: flex-end; gap: 4px; padding-bottom: 6px; border-bottom: 1px solid var(--color-divider); }
            .pv-chart span { flex: 1; height: var(--h); border-radius: 2px 2px 0 0; background: var(--color-navy); }
            .pv-chart span:nth-child(3n+2) { background: var(--color-accent); }
            .pv-rows { display: flex; flex-direction: column; }
            .pv-row { display: flex; align-items: center; gap: 10px; height: 26px; border-bottom: 1px solid rgba(var(--ink), 0.08); }
            .pv-dot { width: 8px; height: 8px; flex: none; border-radius: 50%; background: var(--color-teal-light); }
            .pv-row .pv-bar { flex: none; max-width: 100%; }
            .pv-caption { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; padding: 0 2px; }

            @media (max-width: 560px) {
                .pv { grid-template-columns: minmax(0, 1fr); }
                .pv-side { display: none; }
                .pv-value { font-size: 15px; }
                .pv-stat { padding: 8px; }
            }

            @media (max-width: 900px) {
                .hero-grid { grid-template-columns: 1fr; gap: 36px; padding: 56px 24px; }
                .hero-band::before { clip-path: polygon(0 62%, 100% 48%, 100% 100%, 0 100%); }
                [dir="rtl"] .hero-band::before { clip-path: polygon(0 48%, 100% 62%, 100% 100%, 0 100%); }
                .feature-grid { grid-template-columns: 1fr; }
                .stat-grid { grid-template-columns: 1fr; gap: 28px; }
            }
        </style>
    @endpush

    <div class="hero-band">
    <div class="container hero-grid">
        <div>
            <div class="tag tag-accent ms-mb-20">{{ __('marketing.hero_tag') }}</div>
            <h1 class="ms-mw-480">{{ __('marketing.hero_title') }}</h1>
            <p class="hero-lead">{{ __('marketing.hero_description', ['name' => config('app.name')]) }}</p>
            <div class="ms-cta-row">
                <a href="{{ route('register') }}" class="btn btn-primary blueprint">
                    <i class="corner tl"></i><i class="corner tr"></i><i class="corner bl"></i><i class="corner br"></i>
                    {{ __('marketing.get_started') }}
                </a>
                <a href="{{ config('app.github_url') }}" class="btn">{{ __('marketing.view_on_github') }}</a>
            </div>
        </div>
        <figure class="blueprint ms-p-6">
            <i class="corner tl"></i><i class="corner tr"></i><i class="corner bl"></i><i class="corner br"></i>
            <div class="pv" aria-hidden="true">
                <div class="pv-side">
                    <x-brand-mark />
                    <span class="pv-item is-active"></span>
                    <span class="pv-item"></span>
                    <span class="pv-item"></span>
                    <span class="pv-item"></span>
                </div>
                <div class="pv-main">
                    <div class="pv-top">
                        <span class="pv-bar" style="width: 34%"></span>
                        <span class="avatar">AD</span>
                    </div>
                    <div class="pv-stats">
                        <div class="pv-stat"><span class="pv-bar" style="width: 60%"></span><span class="pv-value">1,248</span></div>
                        <div class="pv-stat"><span class="pv-bar" style="width: 48%"></span><span class="pv-value">8,932</span></div>
                        <div class="pv-stat"><span class="pv-bar" style="width: 72%"></span><span class="pv-value">146</span></div>
                    </div>
                    <div class="pv-chart">
                        @foreach ([38, 52, 44, 61, 57, 70, 66, 82, 74, 69, 88, 79, 93, 86] as $height)
                            <span style="--h: {{ $height }}%"></span>
                        @endforeach
                    </div>
                    <div class="pv-rows">
                        @foreach ([44, 58, 36] as $width)
                            <div class="pv-row"><span class="pv-dot"></span><span class="pv-bar" style="width: {{ $width }}%"></span></div>
                        @endforeach
                    </div>
                </div>
            </div>
            <figcaption class="pv-caption">
                <span class="ms-code-chip">dashboard preview</span>
                <span class="text-muted ms-fs-12">sample data</span>
            </figcaption>
        </figure>
    </div>
    </div>

    <div class="container ms-pt-96 ms-pb-96" id="features">
        <div class="ms-mw-560-mb44">
            <h2>{{ __('docs.features') }}</h2>
            <p class="text-muted">{{ __('docs.features_body') }}</p>
        </div>

        <div class="feature-grid">
            @foreach ([
                ['Security', __('docs.security'), __('docs.security_body')],
                ['People', __('docs.people'), __('docs.people_body')],
                ['Data', __('docs.data'), __('docs.data_body')],
                ['Trust', __('docs.trust'), __('docs.trust_body')],
                ['Config', __('docs.config'), __('docs.config_body')],
                ['Speed', __('docs.speed'), __('docs.speed_body')],
            ] as [$kicker, $title, $body])
                <div class="feature-cell">
                    <div class="card-kicker">{{ $kicker }}</div>
                    <div class="card-title">{{ $title }}</div>
                    <p class="card-body">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="stat-band">
        <div class="container stat-grid">
            <div><div class="stat-value">&lt;10 min</div><div class="stat-label">from clone to running admin</div></div>
            <div><div class="stat-value">12+</div><div class="stat-label">Livewire modules included</div></div>
            <div><div class="stat-value">Laravel 13</div><div class="stat-label">on the latest framework</div></div>
        </div>
    </div>
</x-layouts.marketing>

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

            /* Static question preview: mirrors the gameplay screen with sample content. */
            figure.blueprint { margin: 0; }
            .hero-grid > * { min-width: 0; }
            .qv { display: flex; flex-direction: column; gap: 14px; padding: 20px; border-radius: 6px; background: var(--color-bg); border: 1px solid var(--color-divider); }
            .qv-top { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
            .qv-timer { display: inline-flex; align-items: center; justify-content: center; width: 44px; height: 44px; border-radius: 50%; border: 3px solid var(--color-accent); font-family: var(--font-heading); font-weight: 700; font-size: 15px; color: var(--color-text); }
            .qv-question { font-family: var(--font-heading); font-weight: 600; font-size: 19px; line-height: 1.35; color: var(--color-text); }
            .qv-answers { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }
            .qv-answer { display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 10px 12px; border-radius: 8px; background: var(--color-surface); border: 1px solid var(--color-divider); color: var(--color-text); font-weight: 500; }
            .qv-answer b { display: inline-flex; align-items: center; justify-content: center; flex: none; width: 24px; height: 24px; border-radius: 6px; background: rgba(var(--ink), 0.08); font-size: 12px; }
            .qv-answer.is-picked { border-color: var(--color-accent); box-shadow: 0 0 0 2px var(--color-accent) inset; }
            .qv-players { display: flex; gap: 6px; }
            .qv-players .avatar { width: 26px; height: 26px; font-size: 10px; }
            .pv-caption { display: flex; align-items: center; justify-content: space-between; gap: 12px; margin-top: 12px; padding: 0 2px; }

            @media (max-width: 560px) {
                .qv-answers { grid-template-columns: minmax(0, 1fr); }
                .qv-question { font-size: 17px; }
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
                @auth
                    <a href="{{ route('dashboard') }}" class="btn btn-primary blueprint">
                        <i class="corner tl"></i><i class="corner tr"></i><i class="corner bl"></i><i class="corner br"></i>
                        {{ __('marketing.dashboard') }}
                    </a>
                @else
                    <a href="{{ route('register') }}" class="btn btn-primary blueprint">
                        <i class="corner tl"></i><i class="corner tr"></i><i class="corner bl"></i><i class="corner br"></i>
                        {{ __('marketing.create_account') }}
                    </a>
                    <a href="{{ route('login') }}" class="btn">{{ __('marketing.sign_in') }}</a>
                @endauth
            </div>
        </div>
        <figure class="blueprint ms-p-6">
            <i class="corner tl"></i><i class="corner tr"></i><i class="corner bl"></i><i class="corner br"></i>
            <div class="qv" aria-hidden="true">
                <div class="qv-top">
                    <span class="tag tag-neutral">{{ __('fakkroun.preview_category') }}</span>
                    <span class="qv-timer">12</span>
                </div>
                <div class="qv-question">{{ __('marketing.preview_question') }}</div>
                <div class="qv-answers">
                    @foreach (explode('|', __('marketing.preview_answers')) as $index => $answer)
                        <div @class(['qv-answer', 'is-picked' => $index === 2])><b>{{ chr(65 + $index) }}</b>{{ $answer }}</div>
                    @endforeach
                </div>
                <div class="qv-top">
                    <div class="qv-players">
                        @foreach (['SA', 'YM', 'LB', 'NK'] as $initials)
                            <span class="avatar">{{ $initials }}</span>
                        @endforeach
                    </div>
                    <span class="text-muted ms-fs-12">4 / 6</span>
                </div>
            </div>
            <figcaption class="pv-caption">
                <span class="ms-code-chip">{{ __('marketing.preview_caption') }}</span>
                <span class="text-muted ms-fs-12">{{ __('fakkroun.sample_question') }}</span>
            </figcaption>
        </figure>
    </div>
    </div>

    <div class="container ms-pt-96 ms-pb-96" id="features">
        <div class="ms-mw-560-mb44">
            <h2>{{ __('marketing.pillars_title') }}</h2>
            <p class="text-muted">{{ __('marketing.pillars_body') }}</p>
        </div>

        <div class="feature-grid">
            @foreach (['play', 'planet', 'languages', 'learn', 'fair', 'community'] as $index => $pillar)
                <div class="feature-cell">
                    <div class="card-kicker">{{ str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="card-title">{{ __('marketing.pillar_'.$pillar) }}</div>
                    <p class="card-body">{{ __('marketing.pillar_'.$pillar.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="stat-band">
        <div class="container stat-grid">
            <div><div class="stat-value">{{ count(\Database\Seeders\FakkrounCategorySeeder::CATEGORIES) }}</div><div class="stat-label">{{ __('marketing.stat_topics') }}</div></div>
            <div><div class="stat-value">{{ count(config('app.available_locales')) }}</div><div class="stat-label">{{ __('marketing.stat_languages') }}</div></div>
            <div><div class="stat-value">4</div><div class="stat-label">{{ __('marketing.stat_answers') }}</div></div>
        </div>
    </div>
</x-layouts.marketing>

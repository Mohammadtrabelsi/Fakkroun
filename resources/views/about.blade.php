<x-layouts.marketing :title="__('about.hero_title').' — '.__('about.title')" :structured-data="$structuredData ?? null" :canonical="url()->current()">
    @push('styles')
        <style>
            .page-head { padding: 72px 48px 56px; }
            .about-stats { display: grid; grid-template-columns: repeat(4, 1fr); gap: 32px; text-align: center; }
            .about-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px; background: var(--color-divider); border: 1px solid var(--color-divider); }
            .about-cell { background: var(--color-bg); padding: 28px; }
            .about-section-head { max-width: 560px; margin-bottom: 44px; }
            .about-steps { display: flex; flex-direction: column; border-top: 1px solid var(--color-divider); }
            .about-step { display: grid; grid-template-columns: 280px 1fr; gap: 32px; align-items: center; padding: 22px 0; border-bottom: 1px solid var(--color-divider); }
            .about-step p { margin: 0; font-size: 14px; opacity: 0.75; }
            .about-dedication { max-width: 720px; margin: 0 auto; text-align: center; }
            .about-dedication h2 { margin-bottom: 16px; }
            .about-dedication p { font-size: 16px; line-height: 1.7; opacity: 0.8; margin: 0; }
            .about-story { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr); gap: 56px; align-items: start; }
            .about-story-copy p { font-size: 16px; line-height: 1.75; opacity: 0.85; margin: 0 0 18px; }
            .about-story-copy p:last-child { margin-bottom: 0; }
            .about-grid-4 { grid-template-columns: repeat(4, 1fr); }
            .about-step-num { font-family: var(--font-heading); font-weight: 600; font-size: 13px; opacity: 0.55; margin-inline-end: 10px; }
            .about-note { margin-top: 24px; font-size: 14px; opacity: 0.75; max-width: 720px; }
            .about-note a { text-decoration: underline; }
            .about-faq { border-top: 1px solid var(--color-divider); max-width: 820px; }
            .about-faq details { border-bottom: 1px solid var(--color-divider); padding: 18px 0; }
            .about-faq summary { cursor: pointer; font-family: var(--font-heading); font-weight: 600; font-size: 16px; list-style: none; display: flex; justify-content: space-between; gap: 16px; }
            .about-faq summary::-webkit-details-marker { display: none; }
            .about-faq summary::after { content: "+"; opacity: 0.6; }
            .about-faq details[open] summary::after { content: "−"; }
            .about-faq details p { margin: 12px 0 0; font-size: 14px; line-height: 1.7; opacity: 0.8; }
            .about-cta { display: flex; align-items: center; justify-content: space-between; gap: 32px; flex-wrap: wrap; }
            .about-cta h2 { color: inherit; font-size: 28px; margin: 0 0 6px; }
            .about-cta p { margin: 0; max-width: 520px; }
            @media (max-width: 900px) {
                .page-head { padding: 48px 24px 40px; }
                .about-stats { grid-template-columns: repeat(2, 1fr); gap: 28px; }
                .about-grid, .about-grid-4 { grid-template-columns: 1fr; }
                .about-story { grid-template-columns: 1fr; gap: 24px; }
                .about-step { grid-template-columns: 1fr; gap: 10px; }
            }
        </style>
    @endpush

    <div class="container page-head">
        <div class="tag tag-accent ms-mb-20">{{ __('about.tag') }}</div>
        <h1 class="ms-mw-560">{{ __('about.hero_title') }}</h1>
        <p class="text-muted ms-lead">{{ __('about.hero_lead') }}</p>
        <div class="ms-cta-row">
            <a href="{{ route('register') }}" class="btn btn-primary">{{ __('marketing.get_started') }}</a>
            <a href="{{ config('app.github_url') }}" class="btn" target="_blank" rel="noopener">{{ __('marketing.view_on_github') }}</a>
        </div>
    </div>

    <div class="stat-band">
        <div class="container about-stats">
            @foreach ([
                [__('about.stat_free_value'), __('about.stat_free_label')],
                [__('about.stat_locales_value'), __('about.stat_locales_label')],
                [__('about.stat_crud_value'), __('about.stat_crud_label')],
                [__('about.stat_stack_value'), __('about.stat_stack_label')],
            ] as [$value, $label])
                <div>
                    <div class="stat-value">{{ $value }}</div>
                    <div class="stat-label">{{ $label }}</div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="story">
        <div class="about-story">
            <div>
                <div class="tag tag-accent ms-mb-20">{{ __('about.story_kicker') }}</div>
                <h2>{{ __('about.story_title') }}</h2>
            </div>
            <div class="about-story-copy">
                @foreach ([1, 2, 3] as $paragraph)
                    <p>{{ __('about.story_p'.$paragraph) }}</p>
                @endforeach
            </div>
        </div>
    </div>

    <div class="container ms-pt-96" id="mission">
        <div class="about-section-head">
            <h2>{{ __('about.mission_title') }}</h2>
            <p class="text-muted">{{ __('about.mission_body') }}</p>
        </div>

        <div class="about-grid">
            @foreach ([1, 2, 3] as $goal)
                <div class="about-cell">
                    <div class="card-kicker">{{ str_pad($goal, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="card-title">{{ __('about.mission_'.$goal) }}</div>
                    <p class="card-body">{{ __('about.mission_'.$goal.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="audience">
        <div class="about-section-head">
            <h2>{{ __('about.audience_title') }}</h2>
            <p class="text-muted">{{ __('about.audience_body') }}</p>
        </div>

        <div class="about-grid about-grid-4">
            @foreach ([1, 2, 3, 4] as $audience)
                <div class="about-cell">
                    <div class="card-title">{{ __('about.audience_'.$audience) }}</div>
                    <p class="card-body">{{ __('about.audience_'.$audience.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="features">
        <div class="about-section-head">
            <h2>{{ __('about.features_title') }}</h2>
            <p class="text-muted">{{ __('about.features_body') }}</p>
        </div>

        <div class="about-grid">
            @foreach ([
                [__('about.feature_auth'), __('about.feature_auth_body')],
                [__('about.feature_two_factor'), __('about.feature_two_factor_body')],
                [__('about.feature_admin'), __('about.feature_admin_body')],
                [__('about.feature_roles'), __('about.feature_roles_body')],
                [__('about.feature_content'), __('about.feature_content_body')],
                [__('about.feature_api'), __('about.feature_api_body')],
                [__('about.feature_i18n'), __('about.feature_i18n_body')],
                [__('about.feature_theme'), __('about.feature_theme_body')],
                [__('about.feature_tracking'), __('about.feature_tracking_body')],
            ] as $index => [$title, $body])
                <div class="about-cell">
                    <div class="card-kicker">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}</div>
                    <div class="card-title">{{ $title }}</div>
                    <p class="card-body">{{ $body }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96">
        <div class="about-section-head">
            <h2>{{ __('about.start_title') }}</h2>
            <p class="text-muted">{{ __('about.start_body') }}</p>
        </div>

        <div class="about-steps">
            @foreach ([1, 2, 3, 4, 5] as $step)
                <div class="about-step">
                    <div class="card-title"><span class="about-step-num">{{ str_pad($step, 2, '0', STR_PAD_LEFT) }}</span>{{ __('about.start_'.$step.'_cmd') }}</div>
                    <p>{{ __('about.start_'.$step.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="rules">
        <div class="about-section-head">
            <h2>{{ __('about.rules_title') }}</h2>
            <p class="text-muted">{{ __('about.rules_body') }}</p>
        </div>

        <div class="about-grid">
            @foreach ([1, 2, 3, 4, 5, 6] as $rule)
                <div class="about-cell">
                    <div class="card-title">{{ __('about.rule_'.$rule) }}</div>
                    <p class="card-body">{{ __('about.rule_'.$rule.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="questions">
        <div class="about-section-head">
            <h2>{{ __('about.quality_title') }}</h2>
            <p class="text-muted">{{ __('about.quality_body') }}</p>
        </div>

        <div class="about-steps">
            @foreach ([1, 2, 3, 4, 5] as $stage)
                <div class="about-step">
                    <div class="card-title"><span class="about-step-num">{{ str_pad($stage, 2, '0', STR_PAD_LEFT) }}</span>{{ __('about.quality_'.$stage) }}</div>
                    <p>{{ __('about.quality_'.$stage.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96" id="partners">
        <div class="about-section-head">
            <div class="tag tag-accent ms-mb-20">{{ __('about.partners_kicker') }}</div>
            <h2>{{ __('about.partners_title') }}</h2>
            <p class="text-muted">{{ __('about.partners_body') }}</p>
        </div>

        <div class="about-grid">
            @foreach ([1, 2, 3] as $offer)
                <div class="about-cell">
                    <div class="card-title">{{ __('about.partners_'.$offer) }}</div>
                    <p class="card-body">{{ __('about.partners_'.$offer.'_body') }}</p>
                </div>
            @endforeach
        </div>

        <p class="about-note">
            {{ __('about.partners_note') }}
            <a href="{{ route('terms') }}">{{ __('about.partners_terms_link') }}</a>
        </p>
    </div>

    <div class="container ms-pt-96" id="faq">
        <div class="about-section-head">
            <h2>{{ __('about.faq_title') }}</h2>
            <p class="text-muted">{{ __('about.faq_body') }}</p>
        </div>

        <div class="about-faq">
            @foreach ([1, 2, 3, 4, 5, 6] as $faq)
                <details>
                    <summary>{{ __('about.faq_'.$faq.'_q') }}</summary>
                    <p>{{ __('about.faq_'.$faq.'_a') }}</p>
                </details>
            @endforeach
        </div>
    </div>

    <div class="container ms-pt-96 ms-pb-96">
        <div class="about-dedication">
            <div class="tag tag-accent ms-mb-20">{{ __('about.dedication_kicker') }}</div>
            <h2>{{ __('about.dedication_title') }}</h2>
            <p>{{ __('about.dedication_body') }}</p>
        </div>
    </div>

    <div class="stat-band">
        <div class="container about-cta">
            <div>
                <h2>{{ __('about.cta_title') }}</h2>
                <p class="stat-label">{{ __('about.cta_body') }}</p>
            </div>
            <a href="{{ route('register') }}" class="btn btn-primary">{{ __('marketing.get_started') }}</a>
        </div>
    </div>
</x-layouts.marketing>

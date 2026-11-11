{{-- About page, built on the public Fakkroun layout and tokens (see welcome.blade.php). --}}
<x-layouts.fakkroun :title="__('about.tag').' — '.config('app.name')" :description="__('about.hero_lead')" :structured-data="$structuredData ?? null" :canonical="route('about')">

    {{-- 1. Hero --}}
    <section class="fk-container grid items-center gap-14 pt-[72px] pb-16 [grid-template-columns:repeat(auto-fit,minmax(min(460px,100%),1fr))]">
        <div class="flex flex-col items-start gap-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-tint-green px-3.5 py-2 text-sm font-semibold text-[#0B5A55]">{{ __('about.tag') }}</span>
            <h1 class="fk-h1 m-0 text-[clamp(48px,6.5vw,84px)]">Fakkroun <span lang="ar" class="text-sea">فكّرون</span></h1>
            <p class="m-0 max-w-[36rem] text-[19px] leading-relaxed text-ink-muted">{{ __('about.hero_lead') }}</p>
            <div class="flex flex-wrap gap-3.5">
                <a href="{{ $playUrl }}" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg">
                    {{ __('about.hero_play') }}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <a href="#story" class="fk-btn fk-btn-outline min-h-14 rounded-2xl px-7 text-lg">{{ __('about.hero_more') }}</a>
            </div>
        </div>

        <div class="relative flex min-h-[420px] items-center justify-center">
            <div class="absolute h-[380px] w-[380px] max-w-[90%] rounded-full bg-tint-green"></div>
            <x-fakkroun.mascot :size="340" details :label="__('home.hero.mascot')" class="relative max-w-[80%]" />
            <span class="absolute end-0 top-6 rounded-full bg-paper px-4 py-2.5 text-sm font-semibold shadow-[0_8px_20px_rgba(16,38,46,0.1)]" aria-hidden="true"><span lang="ar">فكّر</span> · {{ __('about.hero_think') }}</span>
        </div>
    </section>

    {{-- 2. Figures --}}
    <section class="px-6">
        <div class="mx-auto grid max-w-[1240px] gap-8 rounded-[36px] bg-sea-deep px-[clamp(24px,5vw,64px)] py-12 text-sand [grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))]">
            @foreach ([['free'], ['locales'], ['crud'], ['stack']] as [$key])
                <div class="flex flex-col gap-1.5">
                    <span class="font-display text-[44px] leading-none font-extrabold text-sun">{{ __('about.stat_'.$key.'_value') }}</span>
                    <span class="text-[15px] leading-normal text-[#C9D3D0]">{{ __('about.stat_'.$key.'_label') }}</span>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 3. On this page --}}
    <nav aria-label="{{ __('about.nav_label') }}" class="fk-container pt-10">
        <ul class="m-0 flex list-none flex-wrap gap-2 p-0">
            @foreach ($sections as $section)
                <li><a href="#{{ $section }}" class="flex min-h-11 items-center rounded-full border border-sand-300 bg-paper px-4 text-[15px] font-semibold text-ink no-underline hover:border-sea hover:text-sea">{{ __('about.nav_'.$section) }}</a></li>
            @endforeach
        </ul>
    </nav>

    {{-- 4. Story --}}
    <section id="story" class="fk-container grid scroll-mt-6 gap-12 pt-24 pb-10 [grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr))]">
        <div class="flex flex-col items-start gap-5">
            <span class="fk-eyebrow">{{ __('about.story_kicker') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.story_title') }}</h2>
            <div class="flex items-center gap-4 rounded-3xl border border-sand-300 bg-paper p-5">
                <x-fakkroun.mascot :size="64" />
                <p class="m-0 leading-relaxed text-ink-muted"><b class="text-ink">{{ __('home.turtle.title') }}</b> {{ __('home.turtle.body') }}</p>
            </div>
        </div>
        <div class="flex flex-col gap-5">
            @foreach ([1, 2, 3] as $paragraph)
                <p class="m-0 text-lg leading-relaxed {{ $paragraph === 1 ? 'text-ink' : 'text-ink-muted' }}">{{ __('about.story_p'.$paragraph) }}</p>
            @endforeach
        </div>
    </section>

    {{-- 5. Mission --}}
    <section id="mission" class="fk-container flex scroll-mt-6 flex-col gap-10 py-[72px]">
        <div class="flex max-w-[40rem] flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('about.nav_mission') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.mission_title') }}</h2>
            <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.mission_body') }}</p>
        </div>
        <div class="grid gap-5 [grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))]">
            @foreach ([[1, 'book'], [2, 'people'], [3, 'shield']] as $index => [$goal, $icon])
                <div class="flex flex-col gap-3 rounded-3xl p-7 {{ $tints[$index][0] }}">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-2xl bg-paper {{ $tints[$index][1] }}">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                    </span>
                    <h3 class="m-0 text-[22px] font-semibold">{{ __('about.mission_'.$goal) }}</h3>
                    <p class="m-0 leading-relaxed text-ink-muted">{{ __('about.mission_'.$goal.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 6. Who it is for --}}
    <section id="audience" class="fk-container flex scroll-mt-6 flex-col gap-10 py-[72px]">
        <div class="flex max-w-[40rem] flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('about.nav_audience') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.audience_title') }}</h2>
            <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.audience_body') }}</p>
        </div>
        <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(260px,100%),1fr))]">
            @foreach ([[1, 'home'], [2, 'class'], [3, 'trophy'], [4, 'flag']] as [$audience, $icon])
                <div class="flex flex-col gap-3.5 rounded-3xl border border-sand-300 bg-paper p-6">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-2xl {{ $tints[$audience % 3][0] }} {{ $tints[$audience % 3][1] }}">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                    </span>
                    <b class="text-xl">{{ __('about.audience_'.$audience) }}</b>
                    <p class="m-0 text-[15px] leading-normal text-ink-muted">{{ __('about.audience_'.$audience.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 7. What Fakkroun stands for --}}
    <section id="values" class="border-y border-sand-300 bg-paper">
        <div class="fk-container flex scroll-mt-6 flex-col gap-10 py-[72px]">
            <div class="flex max-w-[40rem] flex-col gap-2.5">
                <span class="fk-eyebrow">{{ __('about.nav_values') }}</span>
                <h2 class="fk-h2 m-0 text-5xl">{{ __('about.features_title') }}</h2>
                <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.features_body') }}</p>
            </div>
            <div class="grid gap-x-10 gap-y-8 [grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))]">
                @foreach ([
                    ['auth', 'leaf'], ['two_factor', 'wave'], ['admin', 'globe'],
                    ['roles', 'people'], ['content', 'book'], ['api', 'chat'],
                    ['i18n', 'scale'], ['theme', 'tag'], ['tracking', 'handshake'],
                ] as $index => [$key, $icon])
                    <div class="flex gap-4">
                        <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl {{ $tints[$index % 3][0] }} {{ $tints[$index % 3][1] }}">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                        </span>
                        <div class="flex flex-col gap-1">
                            <b class="text-lg">{{ __('about.feature_'.$key) }}</b>
                            <p class="m-0 text-[15px] leading-normal text-ink-muted">{{ __('about.feature_'.$key.'_body') }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    {{-- 8. How a game works --}}
    <section id="how" class="fk-container flex scroll-mt-6 flex-col gap-10 pt-24 pb-[72px]">
        <div class="flex max-w-[40rem] flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('about.nav_how') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.start_title') }}</h2>
            <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.start_body') }}</p>
        </div>
        <ol class="m-0 grid list-none gap-5 p-0 [grid-template-columns:repeat(auto-fit,minmax(min(220px,100%),1fr))]">
            @foreach ([1, 2, 3, 4, 5] as $step)
                <li class="flex flex-col gap-3 rounded-3xl border border-sand-300 bg-paper p-6">
                    <span class="font-display text-[52px] leading-none font-extrabold text-sea">{{ $step }}</span>
                    <h3 class="m-0 text-xl font-semibold">{{ __('about.start_'.$step.'_cmd') }}</h3>
                    <p class="m-0 text-[15px] leading-relaxed text-ink-muted">{{ __('about.start_'.$step.'_body') }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- 9. Rules --}}
    <section id="rules" class="fk-container grid scroll-mt-6 items-start gap-12 py-[72px] [grid-template-columns:repeat(auto-fit,minmax(min(400px,100%),1fr))]">
        <div class="flex flex-col gap-[18px] lg:sticky lg:top-8">
            <span class="fk-eyebrow">{{ __('about.nav_rules') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.rules_title') }}</h2>
            <p class="m-0 max-w-[32rem] text-lg leading-relaxed text-ink-muted">{{ __('about.rules_body') }}</p>
            {{-- Mini answer card, same visual language as the home "learn" card. --}}
            <div class="flex max-w-[26rem] flex-col gap-3 rounded-[28px] border border-sand-300 bg-paper p-6 shadow-[0_18px_40px_rgba(16,38,46,0.08)]" aria-hidden="true">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-tint-sea px-3 py-[5px] text-[13px] font-semibold text-[#0B5A55]">{{ __('home.hero.preview_category') }}</span>
                    <span class="font-display text-xl font-extrabold text-sun-text">12s</span>
                </div>
                <p class="m-0 text-lg leading-snug font-semibold">{{ __('home.hero.preview_question') }}</p>
                <div class="flex items-center gap-3 rounded-2xl border-2 border-correct bg-tint-green p-3 font-semibold"><span class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-correct text-paper">{{ app()->getLocale() === 'ar' ? 'أ' : 'A' }}</span>{{ __('home.hero.preview_a') }}<b class="ms-auto text-sea">+120</b></div>
            </div>
        </div>
        <div class="flex flex-col gap-3">
            @foreach ([[1, 'check'], [2, 'bolt'], [3, 'brain'], [4, 'lock'], [5, 'chart'], [6, 'keys']] as [$rule, $icon])
                <div class="flex gap-4 rounded-[20px] border border-sand-300 bg-paper p-5">
                    <span class="flex h-11 w-11 flex-none items-center justify-center rounded-xl bg-tint-sea text-sea">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icon === 'check' ? $check : $icons[$icon] }}"/></svg>
                    </span>
                    <div class="flex flex-col gap-1">
                        <b class="text-lg">{{ __('about.rule_'.$rule) }}</b>
                        <p class="m-0 text-[15px] leading-normal text-ink-muted">{{ __('about.rule_'.$rule.'_body') }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 10. Where questions come from (dark band, like the home community block) --}}
    <section id="questions" class="scroll-mt-6 px-6 py-6">
        <div class="mx-auto grid max-w-[1240px] items-center gap-10 rounded-[36px] bg-sea-deep p-[clamp(28px,5vw,64px)] text-sand [grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr))]">
            <div class="flex flex-col items-start gap-[18px]">
                <span class="text-sm font-semibold tracking-[.08em] text-sun uppercase">{{ __('about.nav_questions') }}</span>
                <h2 class="fk-h2 m-0 text-[46px]">{{ __('about.quality_title') }}</h2>
                <p class="m-0 text-lg leading-relaxed text-[#C9D3D0]">{{ __('about.quality_body') }}</p>
                <a href="{{ route('questions.submit') }}" class="fk-btn min-h-[52px] rounded-[14px] bg-sun px-6 text-ink hover:text-ink">{{ __('home.community.cta') }}</a>
            </div>
            <ol class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach ([1, 2, 3, 4, 5] as $stage)
                    <li class="flex items-start gap-4 rounded-[18px] bg-sea-deep-2 px-5 py-4">
                        <span class="w-6 flex-none font-display text-2xl font-extrabold text-sun">{{ $stage }}</span>
                        <div><b>{{ __('about.quality_'.$stage) }}</b><div class="text-[15px] leading-normal text-[#C9D3D0]">{{ __('about.quality_'.$stage.'_body') }}</div></div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 11. Partners --}}
    <section id="partners" class="fk-container flex scroll-mt-6 flex-col gap-10 py-[72px]">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div class="flex max-w-[40rem] flex-col gap-2.5">
                <span class="fk-eyebrow">{{ __('about.partners_kicker') }}</span>
                <h2 class="fk-h2 m-0 text-5xl">{{ __('about.partners_title') }}</h2>
                <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.partners_body') }}</p>
            </div>
            <a href="{{ config('fakkroun.brand.partner_contact_url') ?: url('/#partners') }}" class="fk-btn fk-btn-outline">{{ __('home.partners.become') }}</a>
        </div>
        <div class="grid gap-5 [grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr))]">
            @foreach ([[1, 'book'], [2, 'tag'], [3, 'flag']] as $index => [$offer, $icon])
                <div class="flex flex-col gap-3 rounded-3xl p-7 {{ $tints[$index][0] }}">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-2xl bg-paper {{ $tints[$index][1] }}">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$icon] }}"/></svg>
                    </span>
                    <h3 class="m-0 text-[22px] font-semibold">{{ __('about.partners_'.$offer) }}</h3>
                    <p class="m-0 leading-relaxed text-ink-muted">{{ __('about.partners_'.$offer.'_body') }}</p>
                </div>
            @endforeach
        </div>
        <p role="note" class="m-0 flex flex-wrap items-center gap-x-2 gap-y-1 rounded-2xl border-2 border-sand-300 bg-paper px-5 py-4 text-[15px] text-ink-muted">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0B6E69" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons['shield'] }}"/></svg>
            {{ __('about.partners_note') }}
            <a href="{{ route('terms') }}" class="fk-link font-semibold">{{ __('about.partners_terms_link') }}</a>
        </p>
    </section>

    {{-- 12. FAQ --}}
    <section id="faq" class="fk-container grid scroll-mt-6 items-start gap-12 py-[72px] [grid-template-columns:repeat(auto-fit,minmax(min(360px,100%),1fr))]">
        <div class="flex flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('about.nav_faq') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('about.faq_title') }}</h2>
            <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.faq_body') }}</p>
        </div>
        <div class="flex flex-col gap-3">
            @foreach ([1, 2, 3, 4, 5, 6] as $faq)
                <details class="group rounded-[20px] border border-sand-300 bg-paper px-5 open:border-sea" @if ($loop->first) open @endif>
                    <summary class="flex min-h-14 cursor-pointer list-none items-center justify-between gap-4 py-3 text-lg font-semibold [&::-webkit-details-marker]:hidden">
                        {{ __('about.faq_'.$faq.'_q') }}
                        <span class="flex h-8 w-8 flex-none items-center justify-center rounded-full bg-tint-sea font-display text-xl text-sea transition-transform group-open:rotate-45" aria-hidden="true">+</span>
                    </summary>
                    <p class="m-0 pb-5 leading-relaxed text-ink-muted">{{ __('about.faq_'.$faq.'_a') }}</p>
                </details>
            @endforeach
        </div>
    </section>

    {{-- 13. Dedication --}}
    <section class="border-y border-sand-300 bg-paper">
        <div class="fk-container flex max-w-[820px] flex-col items-center gap-4 py-16 text-center">
            <span class="rounded-full bg-tint-sun px-3.5 py-2 text-sm font-semibold text-sun-text">{{ __('about.dedication_kicker') }}</span>
            <h2 class="fk-h2 m-0 text-[36px]">{{ __('about.dedication_title') }}</h2>
            <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('about.dedication_body') }}</p>
        </div>
    </section>

    {{-- 14. Orange CTA band --}}
    <section class="px-6 py-[88px]">
        <div class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-7 rounded-[36px] bg-sun px-[clamp(24px,5vw,64px)] py-14">
            <div class="flex max-w-[40rem] flex-col gap-2">
                <h2 class="fk-h2 m-0 text-[clamp(36px,5vw,52px)] leading-none text-ink">{{ __('about.cta_title') }}</h2>
                <p class="m-0 text-lg font-semibold text-ink">{{ __('about.cta_body') }}</p>
            </div>
            <a href="{{ $playUrl }}" class="fk-btn min-h-[60px] rounded-[18px] bg-ink px-8 text-[19px] text-sand hover:text-sand">{{ __('home.cta.button') }}</a>
        </div>
    </section>
</x-layouts.fakkroun>

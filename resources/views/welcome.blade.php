{{-- Home (fakkroun-design/design/Main.dc.html), sections in the design's order. --}}
@php
    $locale = app()->getLocale();
    $priority = \App\Models\Category::query()->roots()->where('is_active', true)->where('is_priority', true)->ordered()->get();
    $others = \App\Models\Category::query()->roots()->where('is_active', true)->where('is_priority', false)->ordered()->get();
    $partners = \App\Models\Partner::query()->visible()->get();
    $playUrl = auth()->check() ? route('rooms.create') : route('login');
    $tiles = ['bg-tint-green text-sea', 'bg-tint-sea text-sea', 'bg-tint-green text-sea', 'bg-tint-sun text-sun-text', 'bg-tint-sun text-sun-text', 'bg-tint-sea text-sea', 'bg-tint-green text-sea'];
    $icons = [
        'leaf' => 'M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16zM4 20c4-4 7-7 11-10',
        'wave' => 'M2 12c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 18c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2M2 6c2.5 0 2.5-2 5-2s2.5 2 5 2 2.5-2 5-2 2.5 2 5 2',
        'shield-leaf' => 'M12 3l8 3v6c0 4.5-3.4 8.3-8 9-4.6-.7-8-4.5-8-9V6zM9 13c0-3 2-5 6-5 0 4-2 6-5 6',
        'thermometer' => 'M14 14.8V4a2 2 0 0 0-4 0v10.8a4 4 0 1 0 4 0zM12 8v7',
        'sun' => 'M12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
        'turtle' => 'M12 6c4.4 0 8 2.7 8 6s-3.6 6-8 6-8-2.7-8-6 3.6-6 8-6zM20 12h2M7 17l-2 3M17 17l2 3M7 7 5 4M17 7l2-3',
        'globe' => 'M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zM2 12h20M12 2a15 15 0 0 1 0 20M12 2a15 15 0 0 0 0 20',
    ];
@endphp
<x-layouts.fakkroun :structured-data="$structuredData ?? null" :canonical="route('home')">

    {{-- 1. Hero --}}
    <section class="fk-container grid items-center gap-14 pt-[72px] pb-[88px] [grid-template-columns:repeat(auto-fit,minmax(min(480px,100%),1fr))]">
        <div class="flex flex-col items-start gap-6">
            <span class="inline-flex items-center gap-2 rounded-full bg-tint-green px-3.5 py-2 text-sm font-semibold text-[#0B5A55]">{{ __('home.hero.pill') }}</span>
            <h1 class="fk-h1 m-0 text-[clamp(52px,7vw,92px)]">{{ __('home.hero.title') }} <span class="text-sea">{{ __('home.hero.title_accent') }}</span></h1>
            @if ($locale !== 'ar')
                <p lang="ar" dir="rtl" class="m-0 font-display text-[32px] font-bold text-sun-text">فكّر، جاوب، تعلّم… واربح!</p>
            @endif
            <p class="m-0 max-w-[34rem] text-[19px] leading-relaxed text-ink-muted">{{ __('home.hero.lead') }}</p>
            <div class="flex w-full flex-wrap items-end gap-3.5">
                <a href="{{ $playUrl }}" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg">
                    {{ __('home.hero.create') }}
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" class="rtl:-scale-x-100"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                </a>
                <form method="GET" action="{{ route('rooms.join') }}" class="flex flex-col gap-1.5">
                    <label for="join-code" class="text-[13px] font-semibold text-ink-muted">{{ __('home.hero.have_code') }}</label>
                    <div class="flex gap-2">
                        <input id="join-code" name="code" placeholder="{{ __('home.hero.code_placeholder') }}" maxlength="8" autocomplete="off" autocapitalize="characters" class="fk-input w-[170px] uppercase tracking-[0.15em] placeholder:normal-case placeholder:tracking-normal placeholder:font-medium">
                        <button type="submit" class="fk-btn fk-btn-outline min-h-[52px] rounded-[14px]">{{ __('home.hero.join') }}</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="relative flex min-h-[540px] items-center justify-center" aria-hidden="false">
            <svg class="absolute inset-0 h-full w-full" aria-hidden="true"><defs><pattern id="hex" width="56" height="97" patternUnits="userSpaceOnUse" patternTransform="scale(0.9)"><path d="M28 0 L56 16 L56 48 L28 64 L0 48 L0 16 Z M28 64 L28 97" fill="none" stroke="#DCCFB4" stroke-width="2"/></pattern><radialGradient id="fade"><stop offset="0.35" stop-color="#fff"/><stop offset="1" stop-color="#000"/></radialGradient><mask id="m"><rect width="100%" height="100%" fill="url(#fade)"/></mask></defs><rect width="100%" height="100%" fill="url(#hex)" mask="url(#m)"/></svg>
            <div class="absolute h-[420px] w-[420px] max-w-[90%] rounded-full bg-tint-green"></div>
            <x-fakkroun.mascot :size="400" details :label="__('home.hero.mascot')" class="relative max-w-[86%]" />

            <div class="absolute start-0 top-3 flex w-[300px] max-w-[80%] flex-col gap-3 rounded-[20px] bg-paper p-[18px] shadow-[0_18px_40px_rgba(16,38,46,0.14)]" aria-hidden="true">
                <div class="flex items-center justify-between">
                    <span class="rounded-full bg-tint-sea px-2.5 py-1 text-xs font-semibold text-[#0B5A55]">{{ __('home.hero.preview_category') }}</span>
                    <span class="font-display text-xl font-extrabold text-sun-text">12s</span>
                </div>
                <p class="m-0 text-[15px] leading-snug font-semibold">{{ __('home.hero.preview_question') }}</p>
                <div class="grid grid-cols-2 gap-2">
                    <span class="rounded-[10px] bg-sea px-2.5 py-[9px] text-[13px] font-semibold text-paper">{{ __('home.hero.preview_a') }}</span>
                    <span class="rounded-[10px] bg-[#F0E8D8] px-2.5 py-[9px] text-[13px]">{{ __('home.hero.preview_b') }}</span>
                </div>
            </div>

            <div class="absolute end-0 bottom-6 flex flex-col gap-2" aria-hidden="true">
                <span class="flex items-center gap-2.5 rounded-full bg-ink py-2.5 ps-2.5 pe-4 text-sm font-medium text-paper"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-sun text-[13px] font-bold text-ink">A</span>{{ __('home.hero.answered_first') }} <b class="text-sun">+120</b></span>
                <span class="flex items-center gap-2.5 rounded-full bg-paper py-2.5 ps-2.5 pe-4 text-sm font-medium text-ink shadow-[0_8px_20px_rgba(16,38,46,0.1)]"><span class="flex h-7 w-7 items-center justify-center rounded-full bg-leaf text-[13px] font-bold">Y</span>{{ __('home.hero.thinking') }}</span>
            </div>
        </div>
    </section>

    {{-- 2. Why a turtle? --}}
    <section class="border-y border-sand-300 bg-paper">
        <div class="fk-container flex flex-wrap items-center gap-x-10 gap-y-5 py-10">
            <x-fakkroun.mascot :size="56" />
            <p class="m-0 flex-[1_1_420px] text-lg leading-relaxed text-ink-muted"><b class="text-ink">{{ __('home.turtle.title') }}</b> {{ __('home.turtle.body') }}</p>
        </div>
    </section>

    {{-- 3. How it works --}}
    <section class="fk-container flex flex-col gap-10 pt-24 pb-10">
        <div class="flex max-w-[40rem] flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('home.how.eyebrow') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('home.how.title') }}</h2>
        </div>
        <ol class="m-0 grid list-none gap-5 p-0 [grid-template-columns:repeat(auto-fit,minmax(min(280px,100%),1fr))]">
            @foreach ([1, 2, 3] as $step)
                <li class="flex flex-col gap-3 rounded-3xl border border-sand-300 bg-paper p-7">
                    <span class="font-display text-[56px] leading-none font-extrabold text-sea">{{ $step }}</span>
                    <h3 class="m-0 text-[22px] font-semibold">{{ __('home.how.s'.$step.'_title') }}</h3>
                    <p class="m-0 leading-relaxed text-ink-muted">{{ __('home.how.s'.$step.'_body') }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    {{-- 4. Categories --}}
    <section id="categories" class="fk-container flex flex-col gap-10 py-[72px]">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div class="flex max-w-[40rem] flex-col gap-2.5">
                <span class="fk-eyebrow">{{ __('home.categories.eyebrow') }}</span>
                <h2 class="fk-h2 m-0 text-5xl">{{ __('home.categories.title') }}</h2>
                <p class="m-0 text-lg leading-relaxed text-ink-muted">{{ __('home.categories.lead') }}</p>
            </div>
            <a href="{{ $playUrl }}" class="fk-btn fk-btn-outline">{{ __('home.categories.see_all') }}</a>
        </div>
        <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(270px,100%),1fr))]">
            @foreach ($priority as $index => $category)
                <a href="{{ $playUrl }}" class="flex flex-col gap-3.5 rounded-3xl border border-sand-300 bg-paper p-6 text-ink no-underline hover:border-sea">
                    <span class="flex h-[52px] w-[52px] items-center justify-center rounded-2xl {{ $tiles[$index % count($tiles)] }}">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="{{ $icons[$category->icon] ?? $icons['globe'] }}"/></svg>
                    </span>
                    <div class="flex flex-col gap-0.5">
                        <b class="text-xl">{{ $category->name }}</b>
                        @if ($locale !== 'ar' && $category->getTranslation('name', 'ar', false))
                            <span lang="ar" dir="rtl" class="text-start text-[15px] text-ink-soft">{{ $category->getTranslation('name', 'ar', false) }}</span>
                        @endif
                    </div>
                    <span class="text-[15px] leading-normal text-ink-muted">{{ $category->description }}</span>
                </a>
            @endforeach
            <div class="flex flex-col gap-3.5 rounded-3xl bg-sea-deep p-6 text-sand">
                <b class="text-xl">{{ __('home.categories.else_title') }}</b>
                <span class="text-[15px] leading-normal text-[#C9D3D0]">{{ __('home.categories.else_body') }}</span>
                <div class="flex flex-wrap gap-2">
                    @foreach ($others as $category)
                        <span class="rounded-full bg-[#1E3C45] px-3 py-1.5 text-sm">{{ $category->name }}</span>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    {{-- 5. Learn as you play --}}
    <section id="learn" class="fk-container grid items-center gap-14 py-[72px] [grid-template-columns:repeat(auto-fit,minmax(min(440px,100%),1fr))]">
        <div class="flex flex-col gap-[18px]">
            <span class="fk-eyebrow">{{ __('home.learn.eyebrow') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('home.learn.title') }}</h2>
            <p class="m-0 max-w-[32rem] text-lg leading-relaxed text-ink-muted">{{ __('home.learn.lead') }}</p>
            <ul class="m-0 flex list-none flex-col gap-3 p-0 text-[17px]">
                @foreach (['b1', 'b2', 'b3'] as $bullet)
                    <li class="flex items-start gap-3"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0B6E69" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>{{ __('home.learn.'.$bullet) }}</li>
                @endforeach
            </ul>
        </div>
        <div class="flex flex-col gap-4 rounded-[28px] border border-sand-300 bg-paper p-7 shadow-[0_18px_40px_rgba(16,38,46,0.08)]">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <span class="rounded-full bg-tint-sea px-3 py-[5px] text-[13px] font-semibold text-[#0B5A55]">{{ __('home.learn.card_meta') }}</span>
                <span class="inline-flex items-center gap-1.5 font-semibold text-sea"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>{{ __('home.learn.card_got') }}</span>
            </div>
            <p class="m-0 text-xl leading-snug font-semibold">{{ __('home.hero.preview_question') }}</p>
            <div class="flex items-center gap-3 rounded-2xl border-2 border-correct bg-tint-green p-3.5 font-semibold"><span class="flex h-8 w-8 items-center justify-center rounded-[10px] bg-correct text-paper">{{ $locale === 'ar' ? 'أ' : 'A' }}</span>{{ __('home.hero.preview_a') }}</div>
            <div class="flex flex-col gap-2 rounded-2xl bg-sand p-[18px]">
                <b class="text-[15px] text-[#A3500F]">{{ __('home.learn.why') }}</b>
                <p class="m-0 leading-relaxed text-ink-muted">{{ __('home.learn.why_body') }}</p>
                <span class="text-[13px] text-ink-soft">{{ __('home.learn.source', ['source' => config('fakkroun.brand.source_placeholder')]) }}</span>
            </div>
        </div>
    </section>

    {{-- 6. Who plays --}}
    <section class="fk-container flex flex-col gap-10 py-[72px]">
        <div class="flex max-w-[40rem] flex-col gap-2.5">
            <span class="fk-eyebrow">{{ __('home.who.eyebrow') }}</span>
            <h2 class="fk-h2 m-0 text-5xl">{{ __('home.who.title') }}</h2>
        </div>
        <div class="grid gap-5 [grid-template-columns:repeat(auto-fit,minmax(min(300px,100%),1fr))]">
            @foreach ([['family', 'bg-tint-green', 'text-[#2E4A3F]'], ['class', 'bg-tint-sea', 'text-[#24474B]'], ['teams', 'bg-tint-sun', 'text-[#5A3618]']] as [$key, $bg, $text])
                <div class="flex flex-col gap-2.5 rounded-3xl p-7 {{ $bg }}">
                    <h3 class="m-0 text-[22px]">{{ __('home.who.'.$key) }}</h3>
                    <p class="m-0 leading-relaxed {{ $text }}">{{ __('home.who.'.$key.'_body') }}</p>
                </div>
            @endforeach
        </div>
    </section>

    {{-- 7. Community --}}
    <section class="px-6 pt-6 pb-[72px]">
        <div class="mx-auto grid max-w-[1240px] items-center gap-10 rounded-[36px] bg-sea-deep p-[clamp(28px,5vw,64px)] text-sand [grid-template-columns:repeat(auto-fit,minmax(min(380px,100%),1fr))]">
            <div class="flex flex-col items-start gap-[18px]">
                <span class="text-sm font-semibold tracking-[.08em] text-sun uppercase">{{ __('home.community.eyebrow') }}</span>
                <h2 class="fk-h2 m-0 text-[46px]">{{ __('home.community.title') }}</h2>
                <p class="m-0 text-lg leading-relaxed text-[#C9D3D0]">{{ __('home.community.lead') }}</p>
                <a href="{{ route('questions.submit') }}" class="fk-btn min-h-[52px] rounded-[14px] bg-sun px-6 text-ink hover:text-ink">{{ __('home.community.cta') }}</a>
            </div>
            <ol class="m-0 flex list-none flex-col gap-3 p-0">
                @foreach ([1, 2, 3, 4] as $step)
                    <li class="flex items-center gap-4 rounded-[18px] bg-sea-deep-2 px-5 py-4"><span class="w-6 font-display text-2xl font-extrabold text-sun">{{ $step }}</span><div><b>{{ __('home.community.s'.$step) }}</b><div class="text-[15px] text-[#C9D3D0]">{{ __('home.community.s'.$step.'_body') }}</div></div></li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- 8. Partners --}}
    <section id="partners" class="fk-container flex flex-col gap-7 pt-10 pb-[72px]">
        <div class="flex flex-wrap items-end justify-between gap-5">
            <div class="flex max-w-[40rem] flex-col gap-2.5">
                <span class="fk-eyebrow">{{ __('home.partners.eyebrow') }}</span>
                <h2 class="fk-h2 m-0 text-[40px]">{{ __('home.partners.title') }}</h2>
                <p class="m-0 text-[17px] leading-relaxed text-ink-muted">{{ __('home.partners.lead') }}</p>
            </div>
            <a href="{{ config('fakkroun.brand.partner_contact_url') ?: route('about') }}" class="fk-btn fk-btn-outline">{{ __('home.partners.become') }}</a>
        </div>
        <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(min(200px,100%),1fr))]">
            @forelse ($partners as $partner)
                <a href="{{ $partner->url ?: '#partners' }}" @if ($partner->url) target="_blank" rel="noopener noreferrer" @endif class="flex min-h-[110px] items-center justify-center rounded-[20px] border-2 border-dashed border-sand-400 bg-paper p-4 font-semibold text-ink-soft no-underline">
                    @if ($partner->logoUrl())
                        <img src="{{ $partner->logoUrl() }}" alt="{{ $partner->name }}" class="max-h-16 max-w-full object-contain">
                    @else
                        {{ $partner->name }}
                    @endif
                </a>
            @empty
                @for ($i = 0; $i < (int) config('fakkroun.brand.partner_placeholders', 4); $i++)
                    <div class="flex min-h-[110px] items-center justify-center rounded-[20px] border-2 border-dashed border-sand-400 font-semibold text-ink-soft">{{ config('fakkroun.brand.partner_placeholder') }}</div>
                @endfor
            @endforelse
        </div>
    </section>

    {{-- 9. Orange CTA band --}}
    <section class="px-6 pb-[88px]">
        <div class="mx-auto flex max-w-[1240px] flex-wrap items-center justify-between gap-7 rounded-[36px] bg-sun px-[clamp(24px,5vw,64px)] py-14">
            <div class="flex flex-col gap-2">
                <h2 class="fk-h2 m-0 text-[52px] leading-none text-ink">{{ __('home.cta.title') }}</h2>
                <p lang="ar" dir="rtl" class="m-0 text-start text-2xl font-semibold text-ink">{{ __('home.cta.subtitle') }}</p>
            </div>
            <a href="{{ $playUrl }}" class="fk-btn min-h-[60px] rounded-[18px] bg-ink px-8 text-[19px] text-sand hover:text-sand">{{ __('home.cta.button') }}</a>
        </div>
    </section>
</x-layouts.fakkroun>

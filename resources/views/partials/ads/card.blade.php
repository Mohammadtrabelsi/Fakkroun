{{--
    One sponsored card (story 16 S3). $ad: AdDelivery with creative loaded; $dark: bool.
    The browser confirms the display only after the card was at least half visible
    for one second; billing happens on the server, once per delivery token.
--}}
@php($dark = $dark ?? true)
<aside aria-label="{{ __('advertising.sponsored') }}"
       @class(['flex flex-wrap items-center gap-4 rounded-2xl border p-4', 'border-sea-line bg-sea-deep-2 text-sand' => $dark, 'border-sand-300 bg-paper text-ink' => ! $dark])
       wire:key="ad-{{ $ad->id }}"
       x-data="{ timer: null }"
       x-init="const seen = new IntersectionObserver((entries) => {
                   if (entries[0].intersectionRatio >= 0.5) { timer ??= setTimeout(() => { seen.disconnect(); $wire.confirmImpression(@js($ad->token)) }, 1000) }
                   else { clearTimeout(timer); timer = null }
               }, { threshold: [0, 0.5] }); seen.observe($el)">
    <span class="rounded-full bg-sun px-2.5 py-0.5 text-xs font-bold text-ink uppercase">{{ __('advertising.sponsored') }}</span>
    @if ($ad->creative->imageUrl())<img src="{{ $ad->creative->imageUrl() }}" alt="{{ $ad->creative->alt_text }}" class="h-12 w-auto rounded-lg">@endif
    <div class="flex min-w-0 flex-1 flex-col">
        <b>{{ $ad->creative->headline }}</b>
        @if ($ad->creative->body)<span @class(['text-sm', 'text-sea-mist' => $dark, 'text-ink-soft' => ! $dark])>{{ $ad->creative->body }}</span>@endif
    </div>
    @if ($ad->creative->link_url)<a href="{{ $ad->creative->link_url }}" target="_blank" rel="noopener noreferrer sponsored" @class(['fk-btn border-2 no-underline', 'border-sea-line text-sand' => $dark, 'border-sand-300 text-ink' => ! $dark])>{{ __('advertising.learn_more') }}</a>@endif
</aside>

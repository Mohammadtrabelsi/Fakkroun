@props(['href' => null, 'dark' => false])
{{-- Mascot + wordmark "Fakkroun" with "فكّرون" under it. --}}
<a href="{{ $href ?? url('/') }}" {{ $attributes->merge(['class' => 'flex items-center gap-2.5 no-underline '.($dark ? 'text-sand' : 'text-ink')]) }}>
    <x-fakkroun.mascot :size="48" :dark="$dark" class="max-sm:h-auto max-sm:w-10" />
    <span class="flex flex-col leading-none">
        <span class="font-display text-[22px] font-extrabold sm:text-[26px]">Fakkroun</span>
        <span lang="ar" dir="rtl" class="text-sm font-semibold {{ $dark ? 'text-sun' : 'text-sea' }}">فكّرون</span>
    </span>
</a>

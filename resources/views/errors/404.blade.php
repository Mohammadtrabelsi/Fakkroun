<x-layouts.fakkroun :title="__('play.not_found.title').' — '.config('app.name')">
    <section class="fk-container flex flex-col items-center gap-6 py-24 text-center">
        <x-fakkroun.mascot :size="220" details :label="__('home.hero.mascot')" />
        <h1 class="fk-h2 m-0 text-[52px]">{{ __('play.not_found.title') }}</h1>
        <p class="m-0 max-w-[32rem] text-lg text-ink-muted">{{ __('play.not_found.body') }}</p>
        <a href="{{ url('/') }}" class="fk-btn fk-btn-primary min-h-14 rounded-2xl px-7 text-lg">{{ __('play.not_found.home') }}</a>
    </section>
</x-layouts.fakkroun>

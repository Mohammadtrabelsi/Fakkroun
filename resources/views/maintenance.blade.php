<x-layouts.marketing :title="config('app.name').' — '.__('maintenance.title')">

    <div class="container maintenance">
        <div class="tag tag-accent ms-mb-20">{{ __('maintenance.badge') }}</div>
        <h1>{{ __('maintenance.title') }}</h1>
        <p class="text-muted ms-lead">{{ __('maintenance.body') }}</p>
    </div>
</x-layouts.marketing>

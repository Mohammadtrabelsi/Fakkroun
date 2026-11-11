<x-layouts.marketing :title="config('app.name').' — Documentation'" :structured-data="$structuredData ?? null" :canonical="url()->current()">

    <div class="container page-head">
        <div class="tag tag-accent ms-mb-20">{{ __('docs.title') }}</div>
        <h1 class="ms-mw-560">{{ __('docs.hero_title', ['name' => config('app.name')]) }}</h1>
        <p class="text-muted ms-lead">{{ __('docs.hero_description') }}</p>
    </div>

    <div class="container ms-pb-96">
        <div class="docs-grid">
            @foreach ([
                [__('docs.getting_started'), __('docs.getting_started_body'), __('docs.readme'), config('app.github_url')],
                [__('docs.authentication'), __('docs.authentication_body'), __('docs.auth_routes'), route('login')],
                [__('docs.two_factor'), __('docs.two_factor_body'), __('docs.two_factor_link'), route('two-factor.settings')],
                [__('docs.roles_permissions'), __('docs.roles_permissions_body'), __('docs.admin_dashboard'), route('admin.dashboard')],
                [__('docs.user_management'), __('docs.user_management_body'), __('docs.users'), route('admin.users.index')],
                [__('docs.content'), __('docs.content_body'), __('docs.posts_categories'), route('admin.posts.index')],
                [__('docs.api'), __('docs.api_body'), __('docs.api_reference'), config('app.github_url').'#api'],
                [__('docs.crud_scaffolding'), __('docs.crud_scaffolding_body'), __('docs.readme'), config('app.github_url').'#scaffolding-a-new-resource'],
                [__('docs.localization'), __('docs.localization_body'), __('docs.localization_link'), config('app.github_url').'#localization'],
                [__('docs.settings_activity_log'), __('docs.settings_activity_log_body'), __('docs.settings'), route('admin.settings')],
            ] as [$title, $body, $linkLabel, $linkUrl])
                <div class="docs-cell">
                    <div class="card-kicker">{{ __('docs.guide') }}</div>
                    <h3>{{ $title }}</h3>
                    <p>{{ $body }}</p>
                    <a href="{{ $linkUrl }}">{{ $linkLabel }} →</a>
                </div>
            @endforeach
        </div>
    </div>
</x-layouts.marketing>

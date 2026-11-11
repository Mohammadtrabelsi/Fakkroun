<x-layouts.fakkroun :title="__('terms.title').' — '.config('app.name')" :canonical="route('terms')">
    <article class="fk-container flex max-w-[960px] flex-col gap-10 pt-12 pb-20">
        <header class="flex flex-col gap-3">
            <span class="fk-eyebrow">{{ __('terms.eyebrow') }}</span>
            <h1 class="fk-h1 m-0 text-[clamp(40px,6vw,64px)]">{{ __('terms.title') }}</h1>
            <p class="m-0 text-lg text-ink-soft">{{ __('terms.lead') }}</p>
            <p class="m-0 text-sm text-ink-soft">{{ __('terms.updated', ['date' => $legal['terms_updated']]) }}</p>
            @if ($legal['terms_draft'])
                <p role="note" class="m-0 rounded-2xl border-2 border-sun bg-sand px-5 py-3 font-semibold">{{ __('terms.draft_notice') }}</p>
            @endif
        </header>

        <nav aria-label="{{ __('terms.contents') }}" class="rounded-3xl border border-sand-300 bg-paper p-6">
            <b class="mb-2 block">{{ __('terms.contents') }}</b>
            <ol class="m-0 flex flex-col gap-1 ps-5">
                @foreach (__('terms.sections') as $key => $section)
                    <li><a href="#{{ $key }}" class="fk-link">{{ $section['title'] }}</a></li>
                @endforeach
            </ol>
        </nav>

        @foreach (__('terms.sections') as $key => $section)
            <section id="{{ $key }}" class="flex scroll-mt-24 flex-col gap-3" aria-labelledby="{{ $key }}-title">
                <h2 id="{{ $key }}-title" class="fk-h2 m-0 text-[30px]">{{ $loop->iteration }}. {{ $section['title'] }}</h2>
                @foreach ($section['paragraphs'] as $paragraph)
                    <p class="m-0 text-[17px] leading-relaxed">{{ __($paragraph, ['age' => $age, 'email' => $legal['contact_email']]) }}</p>
                @endforeach

                @if ($key === 'accounts')
                    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(260px,1fr))]">
                        @foreach (__('terms.account_types') as $type)
                            <div class="flex flex-col gap-2 rounded-3xl border border-sand-300 bg-paper p-5">
                                <b class="font-display text-xl text-sea">{{ $type['name'] }}</b>
                                <ul class="m-0 flex flex-col gap-1 ps-5">
                                    @foreach ($type['points'] as $point)<li>{{ $point }}</li>@endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if ($key === 'advertising')
                    {{-- The advertiser policy chart. --}}
                    <div class="overflow-x-auto rounded-3xl border border-sand-300 bg-paper">
                        <table class="w-full border-collapse text-start text-[15px]">
                            <caption class="px-5 pt-4 text-start font-semibold">{{ __('terms.policy.caption') }}</caption>
                            <thead>
                                <tr class="border-b border-sand-300">
                                    <th scope="col" class="p-4 text-start">{{ __('terms.policy.principle') }}</th>
                                    <th scope="col" class="p-4 text-start">✓ {{ __('terms.policy.accepted') }}</th>
                                    <th scope="col" class="p-4 text-start">✗ {{ __('terms.policy.refused') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (__('terms.policy.rows') as $row)
                                    <tr class="border-b border-sand-200 align-top last:border-0">
                                        <th scope="row" class="p-4 text-start font-display text-lg text-sea">{{ $row['principle'] }}</th>
                                        <td class="p-4"><ul class="m-0 flex flex-col gap-1 ps-4">@foreach ($row['accepted'] as $item)<li>{{ $item }}</li>@endforeach</ul></td>
                                        <td class="p-4"><ul class="m-0 flex flex-col gap-1 ps-4">@foreach ($row['refused'] as $item)<li>{{ $item }}</li>@endforeach</ul></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="m-0 mt-2 font-display text-2xl">{{ __('terms.formats.title') }}</h3>
                    <div class="overflow-x-auto rounded-3xl border border-sand-300 bg-paper">
                        <table class="w-full border-collapse text-start text-[15px]">
                            <thead>
                                <tr class="border-b border-sand-300">
                                    <th scope="col" class="p-4 text-start">{{ __('terms.formats.format') }}</th>
                                    <th scope="col" class="p-4 text-start">{{ __('terms.formats.where') }}</th>
                                    <th scope="col" class="p-4 text-start">{{ __('terms.formats.counted') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach (__('terms.formats.rows') as $row)
                                    <tr class="border-b border-sand-200 align-top last:border-0">
                                        <th scope="row" class="p-4 text-start font-semibold">{{ $row[0] }}</th>
                                        <td class="p-4">{{ $row[1] }}</td>
                                        <td class="p-4">{{ $row[2] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <h3 class="m-0 mt-2 font-display text-2xl">{{ __('terms.packs.title') }}</h3>
                    <div class="grid gap-4 [grid-template-columns:repeat(auto-fit,minmax(220px,1fr))]">
                        @foreach (__('terms.packs.items') as $pack)
                            <div class="flex flex-col gap-2 rounded-3xl border border-sand-300 bg-paper p-5">
                                <b class="font-display text-xl text-sea">{{ $pack['name'] }}</b>
                                <span class="text-ink-soft">{{ $pack['for'] }}</span>
                                <span class="text-sm">{{ __('terms.packs.price_placeholder') }}</span>
                            </div>
                        @endforeach
                    </div>
                    <ul class="m-0 flex flex-col gap-1 ps-5">
                        @foreach (__('terms.packs.rules') as $rule)<li>{{ $rule }}</li>@endforeach
                    </ul>
                @endif
            </section>
        @endforeach
    </article>
</x-layouts.fakkroun>

{{--
    Summary and per-row errors of a spreadsheet import
    (App\Support\Spreadsheets\ImportResult::toArray()). Presentation only.
--}}
@props(['result'])

<div class="card ms-stack-16" role="status">
    <div class="stat-cards ms-mb-0">
        @foreach ([
            ['imports.created', $result['created']],
            ['imports.updated', $result['updated']],
            ['imports.failed', $result['failed']],
        ] as [$label, $value])
            <div>
                <div class="card-kicker">{{ __($label) }}</div>
                <div class="stat-value">{{ $value }}</div>
            </div>
        @endforeach
    </div>

    @if ($result['skipped'] > 0)
        <p class="text-muted ms-fs-13">{{ __('imports.skipped', ['count' => $result['skipped'], 'max' => config('spreadsheets.max_import_rows')]) }}</p>
    @endif

    @if ($result['errors'] !== [])
        <div class="row-cards-wrap">
            <div class="row-cards">
                    @foreach ($result['errors'] as $error)
                        <div class="row-card">
                            <div class="row-card-field text-muted" data-label="{{ __('imports.row') }}">{{ $error['row'] }}</div>
                            <div class="row-card-field" data-label="{{ __('imports.problem') }}">{{ implode(' ', $error['messages']) }}</div>
                        </div>
                    @endforeach
            </div>
        </div>
        @if ($result['unreported'] > 0)
            <p class="text-muted ms-fs-13">{{ __('imports.more_errors', ['count' => $result['unreported']]) }}</p>
        @endif
    @endif
</div>

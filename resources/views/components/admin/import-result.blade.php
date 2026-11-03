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
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th style="width:80px">{{ __('imports.row') }}</th>
                        <th>{{ __('imports.problem') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($result['errors'] as $error)
                        <tr>
                            <td class="text-muted">{{ $error['row'] }}</td>
                            <td>{{ implode(' ', $error['messages']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @if ($result['unreported'] > 0)
            <p class="text-muted ms-fs-13">{{ __('imports.more_errors', ['count' => $result['unreported']]) }}</p>
        @endif
    @endif
</div>

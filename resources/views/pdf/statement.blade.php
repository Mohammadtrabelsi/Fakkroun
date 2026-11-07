{{-- Monthly usage statement for an advertiser (story 16 S11), from the ledger. --}}
@extends('pdf.layout')

@php($money = fn (int $minor) => number_format($minor / 100, 2).' '.$statement['currency'])

@section('title', __('advertising.invoices.statement_title', ['month' => $statement['month']->translatedFormat('F Y')]))

@section('header', __('advertising.invoices.statement'))

@section('styles')
    table.lines { width: 100%; border-collapse: collapse; margin: 14pt 0; }
    table.lines th { text-align: left; border-bottom: 0.8pt solid #24466F; padding: 4pt 2pt; font-size: 9.5pt; color: #24466F; }
    table.lines td { border-bottom: 0.5pt solid #d5dbe1; padding: 5pt 2pt; }
    table.lines td.num, table.lines th.num { text-align: right; }
@endsection

@section('content')
    <h1>{{ __('advertising.invoices.statement_title', ['month' => $statement['month']->translatedFormat('F Y')]) }}</h1>

    <table class="meta">
        <tr><th>{{ __('advertising.advertiser') }}</th><td>{{ $advertiser->name }}</td></tr>
        <tr><th>{{ __('advertising.invoices.seller') }}</th><td>{{ $seller['name'] }}</td></tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>{{ __('advertising.entry') }}</th><th class="num">{{ __('advertising.quantity') }}</th><th class="num">{{ __('advertising.amount') }}</th></tr>
        </thead>
        <tbody>
            @foreach ($statement['rows'] as $row)
                <tr>
                    <td>{{ __('advertising.credit_types.'.$row['credit_type']) }}</td>
                    <td class="num">{{ number_format($row['quantity']) }}</td>
                    <td class="num">{{ $money($row['amount_minor']) }}</td>
                </tr>
            @endforeach
            @if ($statement['refunds_minor'] !== 0)
                <tr><td>{{ __('advertising.entry_types.reversal') }}</td><td class="num">—</td><td class="num">{{ $money($statement['refunds_minor']) }}</td></tr>
            @endif
        </tbody>
    </table>

    <p style="text-align: right; font-weight: bold;">{{ __('advertising.invoices.total_used') }}: {{ $money($statement['total_minor']) }}</p>
    <p class="small muted">{{ __('advertising.invoices.statement_note') }}</p>
@endsection

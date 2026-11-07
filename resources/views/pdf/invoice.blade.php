{{-- Advertiser pack invoice or credit note (story 16 S11). Values come from the immutable record. --}}
@extends('pdf.layout')

@php
    $money = fn (int $minor) => number_format($minor / 100, 2).' '.$invoice->currency;
    $title = $invoice->isCreditNote() ? __('advertising.invoices.credit_note') : __('advertising.invoices.invoice');
@endphp

@section('title', $title.' '.$invoice->number)

@section('header', $title)

@section('styles')
    table.lines { width: 100%; border-collapse: collapse; margin: 14pt 0; }
    table.lines th { text-align: left; border-bottom: 0.8pt solid #24466F; padding: 4pt 2pt; font-size: 9.5pt; color: #24466F; }
    table.lines td { border-bottom: 0.5pt solid #d5dbe1; padding: 5pt 2pt; }
    table.lines td.num, table.lines th.num { text-align: right; }
    table.parties { width: 100%; border-collapse: collapse; margin-bottom: 8pt; }
    table.parties td { width: 50%; vertical-align: top; padding: 0 8pt 0 0; }
    .total { font-size: 13pt; font-weight: bold; text-align: right; margin-top: 6pt; }
@endsection

@section('content')
    <h1>{{ $title }} {{ $invoice->number }}</h1>

    <table class="meta">
        <tr><th>{{ __('advertising.invoices.issued_at') }}</th><td>{{ $invoice->issued_at->translatedFormat('Y-m-d') }}</td></tr>
        @if ($invoice->payment_reference)
            <tr><th>{{ __('advertising.payment_reference') }}</th><td>{{ $invoice->payment_reference }}</td></tr>
        @endif
        @if ($cancels)
            <tr><th>{{ __('advertising.invoices.cancels') }}</th><td>{{ $cancels->number }}</td></tr>
        @endif
        @if ($invoice->creditNote)
            <tr><th>{{ __('advertising.invoices.cancelled_by') }}</th><td>{{ $invoice->creditNote->number }}</td></tr>
        @endif
    </table>

    <table class="parties">
        <tr>
            <td>
                <h2>{{ __('advertising.invoices.seller') }}</h2>
                <p><b>{{ $invoice->seller['name'] ?? '' }}</b><br>{!! nl2br(e($invoice->seller['address'] ?? '')) !!}<br>
                    <span class="small">{{ __('advertising.invoices.tax_id') }}: {{ $invoice->seller['tax_id'] ?? '' }}</span></p>
            </td>
            <td>
                <h2>{{ __('advertising.invoices.buyer') }}</h2>
                <p><b>{{ $invoice->buyer['name'] ?? '' }}</b><br>{{ $invoice->buyer['email'] ?? '' }}@if (! empty($invoice->buyer['website']))<br>{{ $invoice->buyer['website'] }}@endif</p>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr><th>{{ __('advertising.invoices.description') }}</th><th class="num">{{ __('advertising.quantity') }}</th><th class="num">{{ __('advertising.amount') }}</th></tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ __('advertising.invoices.pack', ['name' => $invoice->package_name]) }}
                    <div class="small muted">
                        {{ __('advertising.credit_types.impression') }}: {{ number_format($invoice->impression_credits) }}
                        · {{ __('advertising.credit_types.sponsored_question') }}: {{ number_format($invoice->sponsored_question_credits) }}
                        @if ($invoice->sponsored_round_credits)· {{ __('advertising.credit_types.sponsored_round') }}: {{ number_format($invoice->sponsored_round_credits) }}@endif
                    </div>
                </td>
                <td class="num">1</td>
                <td class="num">{{ $money($invoice->amount_minor) }}</td>
            </tr>
        </tbody>
    </table>

    <p class="total">{{ __('advertising.invoices.total') }}: {{ $money($invoice->amount_minor) }}</p>

    @if ($invoice->note)
        <p><b>{{ __('advertising.invoices.reason') }}:</b> {{ $invoice->note }}</p>
    @endif

    <p class="small muted">{{ __('advertising.invoices.paid_per_pack') }}</p>
    @if (! empty($invoice->seller['payment_terms']))
        <p class="small muted">{{ $invoice->seller['payment_terms'] }}</p>
    @endif
@endsection

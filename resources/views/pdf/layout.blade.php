{{--
    Shared layout for every generated PDF (App\Services\PdfService).
    Header and footer are fixed elements, which the PDF engines repeat on each
    page inside the page margins set from config/pdf.php. Keep the CSS simple:
    the default DomPDF engine supports CSS 2.1 plus a subset of CSS 3.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ config('app.available_locales.'.app()->getLocale().'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <title>@yield('title', $document['siteName'])</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11pt; line-height: 1.55; color: #1f2933; margin: 0; }
        h1 { font-size: 20pt; line-height: 1.25; margin: 0 0 8pt; color: #24466F; }
        h2 { font-size: 14pt; margin: 18pt 0 6pt; color: #24466F; }
        p { margin: 0 0 9pt; }
        .muted { color: #6b7785; }
        .small { font-size: 9pt; }
        table.meta { width: 100%; border-collapse: collapse; margin: 10pt 0 16pt; font-size: 9.5pt; }
        table.meta th { text-align: left; width: 28%; color: #6b7785; font-weight: normal; padding: 3pt 0; }
        table.meta td { padding: 3pt 0; }
        .pdf-header { position: fixed; top: -15mm; left: 0; right: 0; height: 10mm; border-bottom: 0.5pt solid #d5dbe1; font-size: 9pt; color: #6b7785; }
        .pdf-footer { position: fixed; bottom: -13mm; left: 0; right: 0; height: 9mm; border-top: 0.5pt solid #d5dbe1; font-size: 8.5pt; color: #6b7785; padding-top: 3pt; }
        .pdf-footer .page-number { float: right; }
        .pdf-footer .page-number:after { content: "{{ __('pdf.page') }} " counter(page) " / " counter(pages); }
        .pdf-header .brand { font-weight: bold; color: #24466F; }
        @yield('styles')
    </style>
</head>
<body>
    <div class="pdf-header">
        <span class="brand">{{ $document['siteName'] }}</span>
        @hasSection('header')
            · @yield('header')
        @endif
    </div>

    <div class="pdf-footer">
        @if ($document['pageNumbers'])
            <span class="page-number"></span>
        @endif
        {{ __('pdf.generated_at', ['date' => $document['generatedAt']->translatedFormat('Y-m-d H:i')]) }}
        @if ($document['supportEmail'])
            · {{ $document['supportEmail'] }}
        @endif
    </div>

    <main>
        @yield('content')
    </main>
</body>
</html>

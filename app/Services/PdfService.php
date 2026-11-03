<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Enums\Unit;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Single entry point for generated PDF documents (spatie/laravel-pdf).
 *
 * Every document is a Blade view extending pdf.layout, which draws the
 * shared header (site name) and footer (generation date, page numbers).
 * Page size, orientation and margins come from config/pdf.php, so a new
 * document type only needs its own view:
 *
 *     return $pdf->document('pdf.invoice', ['invoice' => $invoice], 'invoice-42');
 *
 * Callers are responsible for authorization before calling this service.
 */
class PdfService
{
    public function __construct(private readonly SettingService $settings) {}

    /**
     * A PDF ready to be returned from a controller (Responsable). Use
     * ->inline() on the result to preview instead of download.
     *
     * @param  array<string, mixed>  $data
     */
    public function document(string $view, array $data, string $filename): PdfBuilder
    {
        $margins = (array) config('pdf.margins');

        return Pdf::view($view, $data + ['document' => $this->documentMeta()])
            ->format((string) config('pdf.format', 'a4'))
            ->orientation((string) config('pdf.orientation', 'portrait'))
            ->margins(
                (float) ($margins['top'] ?? 20),
                (float) ($margins['right'] ?? 16),
                (float) ($margins['bottom'] ?? 20),
                (float) ($margins['left'] ?? 16),
                Unit::Millimeter,
            )
            ->name($this->filename($filename));
    }

    /**
     * Shared values the layout's header and footer print.
     *
     * @return array{siteName: string, supportEmail: string|null, generatedAt: Carbon, pageNumbers: bool}
     */
    public function documentMeta(): array
    {
        $setting = $this->settings->current();

        return [
            'siteName' => (string) ($setting->site_name ?: config('app.name')),
            'supportEmail' => $setting->support_email,
            'generatedAt' => now(),
            'pageNumbers' => (bool) config('pdf.page_numbers', true),
        ];
    }

    /**
     * Safe, ASCII download name ending in .pdf.
     */
    public function filename(string $name): string
    {
        return (Str::slug(Str::beforeLast($name, '.pdf')) ?: 'document').'.pdf';
    }
}

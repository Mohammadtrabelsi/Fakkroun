<?php

/*
| Application defaults for generated PDF documents (App\Services\PdfService).
| The rendering engine itself is configured in config/laravel-pdf.php:
| "dompdf" (default, pure PHP, no browser needed) or "browsershot"/"chrome"
| (headless Chrome: better CSS and right-to-left/Arabic text shaping).
*/

return [

    /* a4 | letter | legal | a3 | a5 … */
    'format' => env('PDF_FORMAT', 'a4'),

    /* portrait | landscape */
    'orientation' => env('PDF_ORIENTATION', 'portrait'),

    /* Page margins in millimetres; header and footer sit inside them. */
    'margins' => [
        'top' => (float) env('PDF_MARGIN_TOP', 22),
        'right' => (float) env('PDF_MARGIN_RIGHT', 16),
        'bottom' => (float) env('PDF_MARGIN_BOTTOM', 20),
        'left' => (float) env('PDF_MARGIN_LEFT', 16),
    ],

    /* Show "Page X of Y" in the footer. */
    'page_numbers' => (bool) env('PDF_PAGE_NUMBERS', true),

];

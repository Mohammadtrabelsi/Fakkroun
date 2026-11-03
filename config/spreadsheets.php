<?php

/*
| CSV / Excel import and export (spatie/simple-excel, App\Services\SpreadsheetService).
| Who may import or export is governed by the existing permissions
| ("manage posts", "manage categories"), not by these settings.
*/

return [

    /* Formats offered for downloads: csv and/or xlsx. */
    'export_formats' => ['csv', 'xlsx'],

    /* Accepted upload types. */
    'import_formats' => ['csv', 'xlsx'],

    /* Largest accepted upload, in kilobytes. */
    'max_import_kb' => (int) env('IMPORT_MAX_KB', 5120),

    /* Rows beyond this are not processed (protects request time and memory). */
    'max_import_rows' => (int) env('IMPORT_MAX_ROWS', 5000),

    /* Rows written per database transaction during an import. */
    'chunk_size' => 200,

    /* Errors reported back to the user; the rest are only counted. */
    'max_reported_errors' => 50,

];

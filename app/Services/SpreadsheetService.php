<?php

namespace App\Services;

use App\Exceptions\DomainActionException;
use App\Support\Spreadsheets\ImportResult;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\SimpleExcel\SimpleExcelReader;
use Spatie\SimpleExcel\SimpleExcelWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generic CSV/XLSX export and import (spatie/simple-excel).
 *
 * Exports stream rows straight to the response, so memory stays flat for any
 * number of rows. Imports read the file lazily, validate each row on its own,
 * write valid rows in chunked transactions and report invalid ones by row
 * number. Entity-specific columns, rules and persistence live in the domain
 * services (e.g. CategoryService); authorization belongs to the caller.
 */
class SpreadsheetService
{
    public const CONTENT_TYPES = [
        'csv' => 'text/csv; charset=UTF-8',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ];

    /**
     * @param  iterable<array<string, mixed>>  $rows  associative rows; the first row's keys are the header
     */
    public function download(iterable $rows, string $basename, string $format): StreamedResponse
    {
        if (! in_array($format, (array) config('spreadsheets.export_formats'), true) || ! isset(self::CONTENT_TYPES[$format])) {
            abort(404);
        }

        $filename = (Str::slug($basename) ?: 'export').'-'.now()->format('Ymd-His').'.'.$format;

        return response()->streamDownload(function () use ($rows, $format): void {
            $writer = SimpleExcelWriter::create('php://output', $format);

            foreach ($rows as $row) {
                $writer->addRow(array_map(fn ($value) => $this->sanitizeCell($value), $row));
            }

            $writer->close();
        }, $filename, [
            'Content-Type' => self::CONTENT_TYPES[$format],
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Validates and persists every row of an uploaded file.
     *
     * Header names are normalised to snake_case ("Name EN" → "name_en").
     * $persist receives a validated row and returns true when it created a
     * record, false when it updated one; it may throw DomainActionException
     * to reject the row with a message.
     *
     * @param  array<string, mixed>  $rules  Laravel validation rules per column
     * @param  Closure(array<string, mixed>): bool  $persist
     * @param  array<int, string>  $requiredHeaders
     */
    public function import(string $path, string $format, array $rules, Closure $persist, array $requiredHeaders = []): ImportResult
    {
        $result = new ImportResult((int) config('spreadsheets.max_reported_errors', 50));
        $maxRows = (int) config('spreadsheets.max_import_rows', 5000);
        $chunkSize = max(1, (int) config('spreadsheets.chunk_size', 200));

        $reader = SimpleExcelReader::create($path, $format)
            ->trimHeaderRow()
            ->formatHeadersUsing(fn (string $header) => Str::snake(Str::lower(trim(preg_replace('/[^\pL\pN]+/u', ' ', $header)))));

        $headers = $reader->getHeaders() ?? [];
        $missing = array_diff($requiredHeaders, $headers);

        if ($missing !== []) {
            $result->fail(1, [__('imports.missing_columns', ['columns' => implode(', ', $missing)])]);

            return $result;
        }

        $reader->getRows()
            ->map(fn (array $row, int $index) => ['line' => $index + 2, 'data' => $row])
            ->chunk($chunkSize)
            ->each(function ($chunk) use ($rules, $persist, $result, $maxRows): bool {
                DB::transaction(function () use ($chunk, $rules, $persist, $result, $maxRows): void {
                    foreach ($chunk as $row) {
                        if ($result->processed() >= $maxRows) {
                            $result->skipped++;

                            continue;
                        }

                        $this->importRow($row['line'], $this->blankToNull($row['data']), $rules, $persist, $result);
                    }
                });

                return true;
            });

        return $result;
    }

    /**
     * Neutralises spreadsheet formula injection: a cell starting with
     * = + - @ (or a tab / carriage return) would be evaluated by Excel or
     * LibreOffice, so it is prefixed with an apostrophe.
     */
    public function sanitizeCell(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $rules
     */
    protected function importRow(int $line, array $data, array $rules, Closure $persist, ImportResult $result): void
    {
        if (array_filter($data, fn ($value) => $value !== null) === []) {
            return; // blank line
        }

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            $result->fail($line, $validator->errors()->all());

            return;
        }

        try {
            // Nested transaction = savepoint: a failing row is rolled back on
            // its own without aborting the rest of the chunk.
            $created = DB::transaction(fn () => $persist($validator->validated()));
            $created ? $result->created++ : $result->updated++;
        } catch (DomainActionException $e) {
            $result->fail($line, [$e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $result->fail($line, [__('imports.row_failed')]);
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    protected function blankToNull(array $row): array
    {
        return array_map(function ($value) {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d H:i:s');
            }

            $value = is_string($value) ? trim($value) : $value;

            return $value === '' ? null : $value;
        }, $row);
    }
}

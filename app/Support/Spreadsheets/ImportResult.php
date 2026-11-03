<?php

namespace App\Support\Spreadsheets;

/**
 * Outcome of a spreadsheet import: how many rows were created, updated or
 * rejected, and why (row numbers match the spreadsheet, header = row 1).
 */
final class ImportResult
{
    /** @var array<int, array{row: int, messages: array<int, string>}> */
    public array $errors = [];

    public int $created = 0;

    public int $updated = 0;

    public int $failed = 0;

    /** Rows ignored because the file exceeded the row limit. */
    public int $skipped = 0;

    public function __construct(private readonly int $maxReportedErrors = 50) {}

    /**
     * @param  array<int, string>  $messages
     */
    public function fail(int $row, array $messages): void
    {
        $this->failed++;

        if (count($this->errors) < $this->maxReportedErrors) {
            $this->errors[] = ['row' => $row, 'messages' => array_values($messages)];
        }
    }

    public function processed(): int
    {
        return $this->created + $this->updated + $this->failed;
    }

    public function unreportedErrors(): int
    {
        return $this->failed - count($this->errors);
    }

    /**
     * Livewire-friendly representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'created' => $this->created,
            'updated' => $this->updated,
            'failed' => $this->failed,
            'skipped' => $this->skipped,
            'errors' => $this->errors,
            'unreported' => $this->unreportedErrors(),
        ];
    }
}

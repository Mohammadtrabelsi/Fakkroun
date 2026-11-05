# Next step 06: Question import screen

> **Priority:** medium · **Effort:** small–medium · **Spec:** §11
> **Depends on:** 03
>
> **Update:** done in `claude/fakkroun-next-steps` (**Admin → Questions → Import**). Not yet executed; see [`14`](14-final-report.md).

## Current state

`App\Domain\Questions\Imports\QuestionImportService` is finished and tested:
- CSV, XLSX and JSON (flat or nested `choices` / `translations`);
- `headers()`, `suggestMapping()` and `preview()` (no writes);
- `start()` stores the file and dispatches `ProcessQuestionImport`, which records counts and row errors in `question_import_batches` / `question_import_errors`;
- duplicates skipped (`imports.skip_duplicates` setting);
- imported questions start as draft or pending (`imports.initial_status`) and are never published.

What's missing is the admin screen.

## Screen (`/admin/questions/import`, `manage questions`)

1. **Upload:** file input (max `fakkroun.questions.import_max_kb`), format inferred from the extension, plus a downloadable template (`QuestionExportService` columns with one sample row per language).
2. **Map:** one select per field, pre-filled by `suggestMapping()`, with required fields marked; plus a default language for files without a `language` column.
3. **Preview:** the first 5 rows from `preview()`, with errors, warnings and duplicate flags.
4. **Start:** `start()`, then redirect to the batch page.
5. **History / batch page:** status (polling while `pending` or `processing`), counts, the first 500 row errors with row numbers, and a CSV download of the errors.

## Also

- Keep the uploaded file between steps 1–3 in a temporary Livewire upload, and only move it to `question-imports/` on start.
- A prune job deletes batch records and errors after 90 days.
- A setting or option to import into a chosen **status** per batch, limited to draft or pending.

## Done when

- A 10,000-row CSV imports on the queue without timing out the request, and the batch page shows the result.
- Tests cover the Livewire screen end to end with `Queue::fake()` and with the sync queue.

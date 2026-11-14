# Question bank

The question bank holds **1374 questions** in 13 categories, each with at least 100 questions. Each question has four answers, one correct answer, an English origin text and French and Arabic translations.

## Where the data lives

- Source files: `database/data/questions/*.json` (one file per batch, in the admin import layout).
- Seeder: `database/seeders/FakkrounQuestionBankSeeder.php`. It is part of the demo tier (`DatabaseSeeder::DEMO`).
- Test: `tests/Feature/Seeders/FakkrounQuestionBankSeederTest.php`.

## How it is loaded

```bash
php artisan db:seed --class='Database\Seeders\FakkrounQuestionBankSeeder'
```

Or run `php artisan db:seed` in full mode, which includes it. The seeder:

1. Reads each JSON file with the same importer an admin upload uses (`QuestionImportService`).
2. Creates every row through `QuestionService::create`, so the normal validation and duplicate checks apply.
3. Stores questions as **pending**. Nothing is published automatically. Moderators approve and publish them in the moderation queue.
4. Skips exact duplicates and rows whose category slug does not exist, so re-running it is safe.

## JSON format

```json
{"category":"history","difficulty":"easy","language":"en","question":"…","choices":["A","B","C","D"],"correct":2,
 "explanation":"optional","reference_text":"source name","translations":{"fr":{"question":"…","choices":["…"]},"ar":{"question":"…","choices":["…"]}}}
```

- `correct` is 1 to 4, the position of the right answer in `choices`. The same positions are used in every translation.
- `difficulty` is `easy`, `medium` or `hard`. `category` is a slug from `FakkrounCategorySeeder`.
- `reference_text` is a source name. It is required before approval in the categories that need a reference (climate, marine, renewable, environmental, sustainable development, green and blue economy, science and history).
- `explanation` is optional. Only the first batch (88 questions) has one.

## Categories

| Category | Questions | File |
|---|---|---|
| Blue economy | 105 | [blue-economy.md](blue-economy.md) |
| Climate change | 103 | [climate-change.md](climate-change.md) |
| Culture | 106 | [culture.md](culture.md) |
| Environmental protection | 104 | [environmental-protection.md](environmental-protection.md) |
| General knowledge | 111 | [general-knowledge.md](general-knowledge.md) |
| Geography | 112 | [geography.md](geography.md) |
| Green economy | 105 | [green-economy.md](green-economy.md) |
| History | 109 | [history.md](history.md) |
| Marine biodiversity | 102 | [marine-biodiversity.md](marine-biodiversity.md) |
| Renewable energy | 100 | [renewable-energy.md](renewable-energy.md) |
| Science & technology | 106 | [science-technology.md](science-technology.md) |
| Sports | 110 | [sports.md](sports.md) |
| Sustainable development | 101 | [sustainable-development.md](sustainable-development.md) |

## Before publishing

- **Facts are unverified.** The questions were written without checking sources. Verify each one before approval.
- **References are category-level.** Each row in a reference-gated category carries one general source for its topic. Replace it with a precise citation per question.
- **Translations need a native review.** French and Arabic were written without a native-speaker check.
- **Some answers may still be wrong.** Review each question and reject any that are not accurate.

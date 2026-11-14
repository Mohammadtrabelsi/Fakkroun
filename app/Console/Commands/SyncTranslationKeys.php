<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class SyncTranslationKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * php artisan translations:sync
     *   --locales=en,fr,ar   Override which locales to sync (default: auto-detect)
     *   --base=en            The reference locale to use as the source of truth
     *   --dry-run            Show what would change without writing files
     *   --clean              Also remove keys that no longer exist in views
     */
    protected $signature = 'translations:sync
                            {--locales= : Comma-separated list of locales to sync (e.g. en,fr,ar)}
                            {--base=en  : Base/reference locale}
                            {--dry-run  : Show changes without writing files}
                            {--clean    : Remove keys from files that are not found in views}';

    protected $description = 'Scan resources/ for translation keys, create missing lang files, and sync keys across all locales.';

    /**
     * Group for strings used without a "file.key" prefix. Its keys are whole
     * sentences, so they are never read as nested paths.
     */
    private const string FLAT_GROUP = 'strings';

    // ── Patterns ──────────────────────────────────────────────────────────────
    // Matches: __('key'), __("key"), trans('key'), trans("key"),
    //          @lang('key'), @lang("key"), trans_choice('key', …)
    // Also handles dot-notation groups: __('auth.failed'), __('validation.required')
    //
    // The closing quote must be followed by "," or ")": a key that is built at
    // run time (__('about.stat_'.$n), __('questions.status.'.$value)) is only a
    // prefix, not a key, and is collected separately by $dynamicPatterns.
    /** @var list<string> */
    private array $patterns = [
        '/__\(\s*[\'"]([^\'"]+)[\'"]\s*[,)]/U',
        '/trans\(\s*[\'"]([^\'"]+)[\'"]\s*[,)]/U',
        '/@lang\(\s*[\'"]([^\'"]+)[\'"]\s*[,)]/U',
        '/trans_choice\(\s*[\'"]([^\'"]+)[\'"]\s*[,)]/U',
    ];

    /** @var list<string> */
    private array $dynamicPatterns = [
        '/(?:__|trans|@lang|trans_choice)\(\s*[\'"]([^\'"]+)[\'"]\s*\./U',
    ];

    /**
     * Key prefixes completed at run time ("about.stat_"); --clean keeps the
     * keys below them.
     *
     * @var list<string>
     */
    private array $dynamicPrefixes = [];

    public function handle(): int
    {
        $isDry = $this->option('dry-run');
        $doClean = $this->option('clean');
        $base = trim($this->option('base') ?? 'en');
        $langPath = lang_path();                    // resources/lang  (Laravel 9+)
        $resPath = resource_path();

        $this->info('');
        $this->line('  <fg=cyan>Laravel Translation Sync</>');
        $this->line('  '.str_repeat('─', 50));

        // ── 1. Collect all translation keys from views / PHP resources ──────
        $this->line("\n  📂 Scanning: <fg=yellow>{$resPath}</>");
        $foundKeys = $this->extractKeysFromDirectory($resPath);

        if ($foundKeys === []) {
            $this->warn('  No translation keys found. Make sure you use __(), trans(), or @lang().');

            return self::SUCCESS;
        }

        $this->line('  <fg=green>✔</> Found <fg=white>'.count($foundKeys).'</> unique key(s).');

        // ── 2. Group keys by their file/group prefix ─────────────────────────
        // e.g. "auth.failed" → group "auth", key "failed"
        //      "Hello world"  → group "strings" (ungrouped strings), key "Hello world"
        $grouped = $this->groupKeys($foundKeys);

        // ── 3. Determine locales ─────────────────────────────────────────────
        $locales = $this->resolveLocales($langPath, $base);
        $this->line('  <fg=green>✔</> Locales: <fg=white>'.implode(', ', $locales).'</>');

        // ── 4. Sync each locale ──────────────────────────────────────────────
        $this->line('');
        $stats = ['created' => 0, 'added' => 0, 'removed' => 0, 'unchanged' => 0];

        foreach ($locales as $locale) {
            $this->line("  🌍 <fg=cyan>{$locale}</>");
            $localePath = $langPath.DIRECTORY_SEPARATOR.$locale;

            if (! File::isDirectory($localePath)) {
                if ($isDry) {
                    $this->line("     <fg=yellow>[dry-run]</> Would create directory: {$localePath}");
                } else {
                    File::makeDirectory($localePath, 0755, true);
                    $this->line('     <fg=green>+</> Created directory.');
                }
                $stats['created']++;
            }

            foreach ($grouped as $group => $keys) {
                $filePath = $localePath.DIRECTORY_SEPARATOR.$group.'.php';
                $this->syncFile($filePath, $group, $keys, $locale, $base, $langPath, $isDry, $doClean, $stats);
            }
        }

        // ── 5. Summary ───────────────────────────────────────────────────────
        $this->line('');
        $this->line('  '.str_repeat('─', 50));
        $this->line("  <fg=green>Directories created :</> {$stats['created']}");
        $this->line("  <fg=green>Keys added          :</> {$stats['added']}");
        $this->line("  <fg=yellow>Keys removed        :</> {$stats['removed']}");
        $this->line("  <fg=white>Files unchanged     :</> {$stats['unchanged']}");
        $this->line('');

        if ($isDry) {
            $this->warn('  ⚠  Dry-run mode — no files were written.');
        } else {
            $this->info('  ✅ Translation files are up to date.');
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Key extraction
    // ──────────────────────────────────────────────────────────────────────────

    private function extractKeysFromDirectory(string $directory): array
    {
        $allKeys = [];
        $dynamic = [];

        $files = File::allFiles($directory);

        foreach ($files as $file) {
            $ext = $file->getExtension();
            // Accept .php and .blade.php
            if (! in_array($ext, ['php', 'twig'])) {
                continue;
            }

            $content = File::get($file->getPathname());
            foreach ($this->patterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $key) {
                        $allKeys[$key] = true;
                    }
                }
            }
            foreach ($this->dynamicPatterns as $pattern) {
                if (preg_match_all($pattern, $content, $matches)) {
                    foreach ($matches[1] as $prefix) {
                        $dynamic[$prefix] = true;
                    }
                }
            }
        }

        $this->dynamicPrefixes = array_keys($dynamic);

        return array_values(array_filter(array_keys($allKeys), fn (string $k): bool => $k !== ''));
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Group keys by file prefix
    // ──────────────────────────────────────────────────────────────────────────

    private function groupKeys(array $keys): array
    {
        $grouped = [];

        foreach ($keys as $key) {
            // If it contains a dot, and the part before the first dot has no whitespace,
            // we assume it's a "group.key" notation.
            if (str_contains((string) $key, '.') && ! preg_match('/\s/', explode('.', (string) $key, 2)[0])) {
                [$group, $subKey] = explode('.', (string) $key, 2);
            } else {
                // Flat strings without a namespace go into "strings.php"
                $group = self::FLAT_GROUP;
                $subKey = $key;
            }

            $grouped[$group][$subKey] = null; // null = placeholder
        }

        return $grouped;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Sync a single translation file
    // ──────────────────────────────────────────────────────────────────────────

    private function syncFile(
        string $filePath,
        string $group,
        array $expectedKeys,   // ['key' => null, …]
        string $locale,
        string $base,
        string $langPath,
        bool $isDry,
        bool $doClean,
        array &$stats
    ): void {
        // Load existing translations for this locale
        $existing = File::exists($filePath)
            ? (include $filePath)   // returns array
            : [];

        if (! is_array($existing)) {
            $existing = [];
        }

        // Load base locale as fallback values (so non-base files have a hint)
        $baseFile = $langPath.DIRECTORY_SEPARATOR.$base.DIRECTORY_SEPARATOR.$group.'.php';
        $baseValues = ($locale !== $base && File::exists($baseFile))
            ? (include $baseFile)
            : [];
        if (! is_array($baseValues)) {
            $baseValues = [];
        }

        $added = [];
        $removed = [];

        $nested = $group !== self::FLAT_GROUP;

        // Add missing keys. Bundles are nested arrays ("hero.title" lives at
        // ['hero']['title']), so a dotted key is looked up and written as a path.
        foreach (array_keys($expectedKeys) as $key) {
            $key = (string) $key;

            if ($nested ? Arr::has($existing, $key) : array_key_exists($key, $existing)) {
                continue;
            }

            // For the base locale use the last key segment, replacing underscores with spaces and capitalizing the first letter.
            // For other locales, use the base translation wrapped in [TODO].
            $label = $nested ? Arr::last(explode('.', $key)) : $key;
            $transformedKey = ucfirst(str_replace('_', ' ', (string) $label));
            $baseValue = $nested ? Arr::get($baseValues, $key) : ($baseValues[$key] ?? null);
            $placeholder = $locale === $base
                ? $transformedKey
                : ('[TODO] '.(is_string($baseValue) ? $baseValue : $transformedKey));

            if ($nested) {
                Arr::set($existing, $key, $placeholder);
            } else {
                $existing[$key] = $placeholder;
            }

            $added[] = $key;
        }

        // Optionally remove stale keys
        if ($doClean) {
            $existingKeys = $nested ? array_keys(Arr::dot($existing)) : array_keys($existing);

            foreach ($existingKeys as $existingKey) {
                $existingKey = (string) $existingKey;

                if ($this->isUsed($existingKey, $group, $expectedKeys, $nested)) {
                    continue;
                }

                if ($nested) {
                    Arr::forget($existing, $existingKey);
                } else {
                    unset($existing[$existingKey]);
                }

                $removed[] = $existingKey;
            }
        }

        if ($added === [] && $removed === []) {
            $stats['unchanged']++;

            return;
        }

        // Report changes
        foreach ($added as $k) {
            $verb = File::exists($filePath) ? '<fg=green>+</>' : '<fg=green>✚</>';
            $this->line("     {$verb} {$group}.{$k}");
            $stats['added']++;
        }
        foreach ($removed as $k) {
            $this->line("     <fg=red>-</> {$group}.{$k}");
            $stats['removed']++;
        }

        if ($isDry) {
            return;
        }

        // Write file
        $content = "<?php\n\nreturn ".$this->arrayToString($existing).";\n";

        if (! File::exists($filePath)) {
            $stats['created']++;
        }

        File::put($filePath, $content);
    }

    /**
     * Whether a key found in a lang file is still referenced: directly, as part
     * of an array a view reads whole (__('terms.policy.rows')), or through a
     * prefix completed at run time (__('about.stat_'.$n)).
     *
     * @param  array<array-key, mixed>  $expectedKeys
     */
    private function isUsed(string $key, string $group, array $expectedKeys, bool $nested): bool
    {
        if (array_key_exists($key, $expectedKeys)) {
            return true;
        }

        if (! $nested) {
            return false;
        }

        foreach (array_keys($expectedKeys) as $expected) {
            if (str_starts_with($key, $expected.'.')) {
                return true;
            }
        }

        foreach ($this->dynamicPrefixes as $prefix) {
            if (str_starts_with($group.'.'.$key, $prefix)) {
                return true;
            }
        }

        return false;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Resolve which locales to process
    // ──────────────────────────────────────────────────────────────────────────

    private function resolveLocales(string $langPath, string $base): array
    {
        $option = $this->option('locales');

        if ($option) {
            $locales = array_map(trim(...), explode(',', $option));
        } elseif (File::isDirectory($langPath)) {
            // Auto-detect from existing directories
            $locales = array_map(
                basename(...),
                array_filter(File::directories($langPath), fn ($d): bool => ! str_starts_with(basename((string) $d), '_'))
            );
        } else {
            $locales = [];
        }

        // Always include the base locale
        if (! in_array($base, $locales)) {
            array_unshift($locales, $base);
        }

        sort($locales);

        return array_unique($locales);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Pretty-print a PHP array (avoids var_export ugliness)
    // ──────────────────────────────────────────────────────────────────────────

    private function arrayToString(array $array, int $indent = 1): string
    {
        $pad = str_repeat('    ', $indent);
        $lines = ['['];

        foreach ($array as $key => $value) {
            $k = is_string($key) ? "'{$this->escapeString($key)}'" : $key;

            if (is_array($value)) {
                $lines[] = $pad."{$k} => ".$this->arrayToString($value, $indent + 1).',';
            } else {
                $v = is_null($value)
                    ? 'null'
                    : (is_bool($value) ? ($value ? 'true' : 'false') : "'{$this->escapeString((string) $value)}'");
                $lines[] = $pad."{$k} => {$v},";
            }
        }

        $closePad = str_repeat('    ', $indent - 1);
        $lines[] = $closePad.']';

        return implode("\n", $lines);
    }

    private function escapeString(string $str): string
    {
        return str_replace(['\\', "'"], ['\\\\', "\\'"], $str);
    }
}

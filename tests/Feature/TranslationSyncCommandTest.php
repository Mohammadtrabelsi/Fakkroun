<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TranslationSyncCommandTest extends TestCase
{
    private string $langPath;

    protected function setUp(): void
    {
        parent::setUp();

        // Redirect the lang directory to a throwaway location so the command
        // writes there instead of mutating the repository's real lang files.
        // Scanning still targets the real resources/ directory (read-only).
        $this->langPath = sys_get_temp_dir().'/transync_'.uniqid();
        File::ensureDirectoryExists($this->langPath);
        app()->useLangPath($this->langPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->langPath);

        parent::tearDown();
    }

    public function test_translations_sync_command_is_registered(): void
    {
        // Regression guard: the command lives in App\Console\Commands and must
        // be discoverable (the directory was previously mis-cased as
        // app/console, which broke PSR-4 autoloading on Linux).
        $this->assertArrayHasKey('translations:sync', Artisan::all());
    }

    public function test_translations_sync_dry_run_succeeds(): void
    {
        $this->artisan('translations:sync', ['--dry-run' => true])
            ->assertSuccessful();
    }

    public function test_dry_run_does_not_write_any_files(): void
    {
        $this->artisan('translations:sync', ['--dry-run' => true, '--locales' => 'en'])
            ->assertSuccessful();

        $this->assertDirectoryDoesNotExist($this->langPath.'/en');
    }

    public function test_run_creates_locale_directories_and_key_files(): void
    {
        $this->artisan('translations:sync', ['--locales' => 'en'])
            ->assertSuccessful();

        $this->assertDirectoryExists($this->langPath.'/en');
        $this->assertNotEmpty(File::files($this->langPath.'/en'));
    }

    public function test_non_base_locale_receives_todo_placeholders(): void
    {
        $this->artisan('translations:sync', ['--locales' => 'en,ar', '--base' => 'en'])
            ->assertSuccessful();

        $this->assertDirectoryExists($this->langPath.'/ar');

        $arContents = collect(File::files($this->langPath.'/ar'))
            ->map(fn ($file) => File::get($file->getPathname()))
            ->implode("\n");

        $this->assertStringContainsString('[TODO]', $arContents);
    }

    public function test_base_locale_values_are_not_marked_todo(): void
    {
        $this->artisan('translations:sync', ['--locales' => 'en', '--base' => 'en'])
            ->assertSuccessful();

        $enContents = collect(File::files($this->langPath.'/en'))
            ->map(fn ($file) => File::get($file->getPathname()))
            ->implode("\n");

        $this->assertStringNotContainsString('[TODO]', $enContents);
    }

    public function test_clean_option_removes_stale_keys_and_adds_scanned_ones(): void
    {
        File::ensureDirectoryExists($this->langPath.'/en');
        $marketingFile = $this->langPath.'/en/marketing.php';
        File::put($marketingFile, "<?php\n\nreturn ['zzz_stale_key' => 'remove me'];\n");

        $this->artisan('translations:sync', ['--locales' => 'en', '--base' => 'en', '--clean' => true])
            ->assertSuccessful();

        $result = include $marketingFile;

        $this->assertIsArray($result);
        $this->assertArrayNotHasKey('zzz_stale_key', $result);
        // The scan should have added real marketing.* keys used across the views.
        $this->assertNotEmpty($result);
    }

    public function test_second_run_is_idempotent(): void
    {
        $this->artisan('translations:sync', ['--locales' => 'en'])->assertSuccessful();

        // Re-running against already-synced files must still succeed and
        // report the files as up to date.
        $this->artisan('translations:sync', ['--locales' => 'en'])
            ->expectsOutputToContain('Translation files are up to date.')
            ->assertSuccessful();
    }

    public function test_nested_keys_that_already_exist_are_left_alone(): void
    {
        // Regression: bundles are nested arrays. The command used to look a
        // dotted key up as one flat key, never found it, and wrote a flat
        // 'hero.title' => 'Hero.title' entry that then overrode the real text.
        File::copyDirectory(base_path('lang'), $this->langPath);

        $this->artisan('translations:sync')
            ->expectsOutputToContain('Keys added          : 0')
            ->assertSuccessful();

        foreach (['en', 'fr', 'ar'] as $locale) {
            $home = include $this->langPath.'/'.$locale.'/home.php';

            $this->assertArrayNotHasKey('hero.title', $home);
            $this->assertSame(
                include base_path('lang/'.$locale.'/home.php'),
                $home,
                "The [{$locale}] bundle must not be rewritten when nothing is missing.",
            );
        }
    }

    public function test_a_missing_nested_key_is_added_as_a_nested_placeholder(): void
    {
        File::copyDirectory(base_path('lang'), $this->langPath);

        foreach (['en', 'fr'] as $locale) {
            $file = $this->langPath.'/'.$locale.'/home.php';
            $home = include $file;
            unset($home['hero']['title']);
            File::put($file, "<?php\n\nreturn ".var_export($home, true).";\n");
        }

        $this->artisan('translations:sync', ['--locales' => 'en,fr'])
            ->assertSuccessful();

        $en = include $this->langPath.'/en/home.php';
        $fr = include $this->langPath.'/fr/home.php';

        $this->assertSame('Title', $en['hero']['title']);
        $this->assertSame('[TODO] Title', $fr['hero']['title']);
        $this->assertArrayNotHasKey('hero.title', $en);
        $this->assertArrayNotHasKey('hero.title', $fr);
    }

    public function test_keys_built_at_run_time_are_not_added_as_prefixes(): void
    {
        $this->artisan('translations:sync', ['--locales' => 'en'])
            ->assertSuccessful();

        // about.blade.php calls __('about.stat_'.$n): a prefix, not a key.
        $about = include $this->langPath.'/en/about.php';

        $this->assertArrayNotHasKey('stat_', $about);
    }

    public function test_clean_keeps_run_time_keys_and_arrays_read_whole(): void
    {
        File::copyDirectory(base_path('lang'), $this->langPath);

        $this->artisan('translations:sync', ['--locales' => 'en', '--clean' => true])
            ->assertSuccessful();

        $original = include base_path('lang/en/terms.php');
        $cleaned = include $this->langPath.'/en/terms.php';

        // __('terms.policy.rows') returns the whole array of rows.
        $this->assertSame($original['policy']['rows'], $cleaned['policy']['rows']);

        // __('about.stat_'.$n) reaches every about.stat_* key.
        $about = include $this->langPath.'/en/about.php';
        $statKeys = array_filter(array_keys(include base_path('lang/en/about.php')), fn (string $key): bool => str_starts_with($key, 'stat_'));

        $this->assertNotEmpty($statKeys);

        foreach ($statKeys as $key) {
            $this->assertArrayHasKey($key, $about);
        }
    }
}

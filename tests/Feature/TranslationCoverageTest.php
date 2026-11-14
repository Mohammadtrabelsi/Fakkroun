<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * House rule: every key exists in every locale, and every key a view uses
 * exists. (translations:sync skips keys built at run time such as
 * "about.stat_".$key, so it cannot serve as this check.)
 */
class TranslationCoverageTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function keys(string $locale): array
    {
        $keys = [];

        foreach (File::files(lang_path($locale)) as $file) {
            $keys += Arr::dot([$file->getFilenameWithoutExtension() => require $file->getPathname()]);
        }

        return $keys;
    }

    public function test_every_locale_has_the_same_keys(): void
    {
        $reference = array_keys($this->keys('en'));

        foreach (array_keys((array) config('app.available_locales')) as $locale) {
            $keys = array_keys($this->keys($locale));

            $this->assertSame([], array_values(array_diff($reference, $keys)), "Keys missing in [{$locale}].");
            $this->assertSame([], array_values(array_diff($keys, $reference)), "Keys only in [{$locale}].");
        }
    }

    public function test_no_translation_is_left_as_a_todo(): void
    {
        foreach (array_keys((array) config('app.available_locales')) as $locale) {
            $todo = array_keys(array_filter($this->keys($locale), fn ($value) => is_string($value) && str_contains($value, '[TODO]')));

            $this->assertSame([], $todo, "Untranslated keys in [{$locale}].");
        }
    }

    public function test_keys_used_in_views_exist(): void
    {
        $known = $this->keys('en');
        $missing = [];

        foreach (File::allFiles(resource_path('views')) as $file) {
            preg_match_all("/(?:__|trans_choice|@lang)\(\s*'([a-z0-9_\-]+\.[A-Za-z0-9_.\-]+)'\s*[,)]/", $file->getContents(), $matches);

            foreach ($matches[1] as $key) {
                // Keys built at run time end with a dot ("questions.status.".$value) and are skipped.
                if (str_ends_with($key, '.') || array_key_exists($key, $known) || Arr::has(Arr::undot($known), $key)) {
                    continue;
                }

                $missing[$key] = $file->getRelativePathname();
            }
        }

        $this->assertSame([], $missing, 'Views use translation keys that do not exist.');
    }
}

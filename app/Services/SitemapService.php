<?php

namespace App\Services;

use App\Exceptions\SitemapGenerationException;
use DOMDocument;
use Generator;
use Illuminate\Support\Facades\File;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\SitemapIndex;
use Spatie\Sitemap\Tags\Url;
use Throwable;

/**
 * Builds the XML sitemap from explicit route names and published records
 * (never by crawling) and publishes it atomically: every file is rendered,
 * validated and written to a temporary file first, then renamed into place,
 * so a failed run always leaves the previous sitemap untouched.
 */
class SitemapService
{
    /** Hosts that must never end up in a production sitemap. */
    private const NON_PUBLIC_HOST = '/(^localhost$|^127\.|^0\.0\.0\.0$|^\[?::1\]?$|\.(test|local|localhost|invalid|example)$)/i';

    public function __construct(private readonly PostService $posts) {}

    /**
     * Generates and publishes the sitemap, returning the number of URLs written.
     *
     * @throws SitemapGenerationException
     */
    public function generate(): int
    {
        $baseUrl = $this->canonicalBaseUrl();
        $path = (string) config('sitemap.path');
        $directory = dirname($path);
        $maxPerFile = max(1, (int) config('sitemap.max_urls_per_file', 50000));

        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new SitemapGenerationException("The sitemap directory [{$directory}] does not exist or is not writable.");
        }

        /** @var array<int, string> $chunks temporary files, one per sitemap file */
        $chunks = [];
        $staged = [];
        $total = 0;

        try {
            $sitemap = Sitemap::create();
            $inChunk = 0;

            foreach ($this->urls($baseUrl) as $url) {
                $sitemap->add($url);
                $total++;

                if (++$inChunk === $maxPerFile) {
                    $chunks[] = $this->stage($directory, $sitemap->render());
                    $sitemap = Sitemap::create();
                    $inChunk = 0;
                }
            }

            if ($inChunk > 0 || $chunks === []) {
                $chunks[] = $this->stage($directory, $sitemap->render());
            }

            // Final file name => staged temporary file.
            if (count($chunks) === 1) {
                $staged = [$path => $chunks[0]];
            } else {
                $index = SitemapIndex::create();

                foreach ($chunks as $i => $tmp) {
                    $staged[$this->chunkPath($path, $i + 1)] = $tmp;
                    $index->add($this->publicUrlFor($baseUrl, $this->chunkPath($path, $i + 1)));
                }

                // The index goes last so it never points at a file not yet in place.
                $staged[$path] = $this->stage($directory, $index->render());
            }
        } catch (Throwable $e) {
            $this->discard(array_merge($chunks, array_values($staged)));

            throw $e instanceof SitemapGenerationException
                ? $e
                : new SitemapGenerationException('Sitemap generation failed: '.$e->getMessage(), previous: $e);
        }

        foreach ($staged as $final => $tmp) {
            if (! @rename($tmp, $final)) {
                $this->discard(array_values($staged));

                throw new SitemapGenerationException("Could not move the generated sitemap into place at [{$final}].");
            }
        }

        $this->removeStaleChunks($path, count($chunks) > 1 ? count($chunks) : 0);

        return $total;
    }

    /**
     * Every canonical, public, indexable URL — de-duplicated and query-string free.
     *
     * @return Generator<int, Url>
     */
    public function urls(string $baseUrl): Generator
    {
        $seen = [];

        $make = function (string $relative) use ($baseUrl, &$seen): ?Url {
            $loc = $this->absolute($baseUrl, $relative);

            if (isset($seen[$loc])) {
                return null;
            }
            $seen[$loc] = true;

            // No changefreq/priority: search engines ignore them, so only reliable data is emitted.
            return Url::create($loc);
        };

        foreach ((array) config('sitemap.static_routes', []) as $route) {
            if ($url = $make(route($route, [], false))) {
                if ($route === 'blog' && $lastmod = $this->posts->lastPublishedModification()) {
                    $url->setLastModificationDate($lastmod);
                }

                yield $url;
            }
        }

        foreach ($this->posts->publishedForSitemap() as $post) {
            if ($url = $make(route('blog.show', $post->slug, false))) {
                if ($post->updated_at) {
                    $url->setLastModificationDate($post->updated_at);
                }

                yield $url;
            }
        }
    }

    /**
     * APP_URL without a trailing slash, validated to be an absolute http(s) URL
     * (and, in production, a real public domain).
     *
     * @throws SitemapGenerationException
     */
    public function canonicalBaseUrl(): string
    {
        $url = rtrim((string) config('app.url'), '/');
        $parts = parse_url($url);
        $host = $parts['host'] ?? null;

        if (! in_array($parts['scheme'] ?? null, ['http', 'https'], true) || ! $host
            || isset($parts['query']) || isset($parts['fragment'])
        ) {
            throw new SitemapGenerationException("APP_URL [{$url}] is not a valid absolute http(s) URL.");
        }

        if (app()->isProduction() && preg_match(self::NON_PUBLIC_HOST, $host)) {
            throw new SitemapGenerationException("APP_URL [{$url}] points at a non-public host; set it to the production domain.");
        }

        return $url;
    }

    /**
     * Public URL of the sitemap (used by robots.txt), or null if it has not been generated yet.
     */
    public function publicUrl(): ?string
    {
        $path = (string) config('sitemap.path');

        return is_file($path)
            ? $this->publicUrlFor(rtrim((string) config('app.url'), '/'), $path)
            : null;
    }

    private function absolute(string $baseUrl, string $relative): string
    {
        // Strip any query string or fragment: the sitemap lists canonical URLs only.
        $relative = strtok($relative, '?#') ?: '/';

        return $baseUrl.'/'.ltrim($relative, '/');
    }

    private function publicUrlFor(string $baseUrl, string $file): string
    {
        $relative = ltrim(str_replace('\\', '/', substr($file, strlen(public_path()))), '/');

        return $baseUrl.'/'.$relative;
    }

    private function chunkPath(string $path, int $number): string
    {
        return preg_replace('/\.xml$/', '', $path)."-{$number}.xml";
    }

    /**
     * Validates the XML and writes it to a world-readable temporary file in the target directory.
     *
     * @throws SitemapGenerationException
     */
    private function stage(string $directory, string $xml): string
    {
        $this->assertValidXml($xml);

        $tmp = tempnam($directory, '.sitemap-');

        if ($tmp === false || file_put_contents($tmp, $xml, LOCK_EX) === false) {
            if ($tmp) {
                File::delete($tmp);
            }

            throw new SitemapGenerationException("Could not write a temporary sitemap file in [{$directory}].");
        }

        // tempnam() creates 0600 files; the web server must be able to read the sitemap.
        @chmod($tmp, 0644);

        return $tmp;
    }

    /**
     * @throws SitemapGenerationException
     */
    private function assertValidXml(string $xml): void
    {
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = (new DOMDocument)->loadXML($xml, LIBXML_NONET);
            $error = libxml_get_last_error();
            libxml_clear_errors();
        } finally {
            libxml_use_internal_errors($previous);
        }

        if (! $loaded || $error) {
            throw new SitemapGenerationException('Generated sitemap is not well-formed XML'.($error ? ': '.trim($error->message) : '.'));
        }
    }

    /**
     * @param  array<int, string>  $files
     */
    private function discard(array $files): void
    {
        foreach (array_unique($files) as $file) {
            if (is_file($file)) {
                File::delete($file);
            }
        }
    }

    /**
     * Removes numbered sitemap files left over from an earlier, larger run.
     */
    private function removeStaleChunks(string $path, int $keep): void
    {
        foreach (File::glob(preg_replace('/\.xml$/', '', $path).'-*.xml') as $file) {
            if (preg_match('/-(\d+)\.xml$/', $file, $m) && (int) $m[1] > $keep) {
                File::delete($file);
            }
        }
    }
}

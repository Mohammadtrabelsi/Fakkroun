<?php

namespace App\Http\Controllers;

use App\Services\SitemapService;
use Illuminate\Http\Response;

/**
 * Serves robots.txt dynamically so the Sitemap line always uses the configured
 * APP_URL instead of a domain hardcoded into a static file.
 */
class RobotsController extends Controller
{
    public function __invoke(SitemapService $sitemap): Response
    {
        $lines = ['User-agent: *', 'Disallow:'];

        if ($url = $sitemap->publicUrl()) {
            $lines[] = '';
            $lines[] = "Sitemap: {$url}";
        }

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}

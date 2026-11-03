<?php

namespace App\Console\Commands;

use App\Services\SitemapService;
use Illuminate\Console\Command;
use Throwable;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate public/sitemap.xml from public routes and published content.';

    public function handle(SitemapService $sitemap): int
    {
        try {
            $count = $sitemap->generate();
        } catch (Throwable $e) {
            report($e);
            $this->error('Sitemap generation failed; the previous sitemap was left unchanged.');
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Sitemap generated with {$count} URL(s): ".$sitemap->publicUrl());

        return self::SUCCESS;
    }
}

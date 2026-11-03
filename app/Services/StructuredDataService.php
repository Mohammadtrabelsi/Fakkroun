<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Str;
use Spatie\SchemaOrg\BreadcrumbList;
use Spatie\SchemaOrg\Graph;
use Spatie\SchemaOrg\Schema;

/**
 * Builds the schema.org JSON-LD for the public pages (spatie/schema-org).
 *
 * Every page gets a single @graph, so each entity appears once and the others
 * reference it by @id instead of repeating it:
 *   - Organization and WebSite on every page,
 *   - BreadcrumbList on inner pages,
 *   - BlogPosting on a post page.
 * Only types this application actually has content for are produced.
 */
class StructuredDataService
{
    public function __construct(private readonly SettingService $settings) {}

    public function enabled(): bool
    {
        return (bool) config('seo.structured_data', true);
    }

    /**
     * Landing page: who publishes the site and how to search it.
     */
    public function forHome(): Graph
    {
        return $this->baseGraph();
    }

    /**
     * A generic public page: the site entities plus its breadcrumb trail.
     *
     * @param  array<string, string>  $trail  name => absolute URL, after "Home"
     */
    public function forPage(array $trail): Graph
    {
        $graph = $this->baseGraph();
        $graph->add($this->breadcrumbs($trail));

        return $graph;
    }

    /**
     * A published post: BlogPosting plus Home › Blog › Post breadcrumbs.
     */
    public function forPost(Post $post): Graph
    {
        $graph = $this->forPage([
            __('marketing.blog') => route('blog'),
            (string) $post->title => $post->publicUrl(),
        ]);

        $article = Schema::blogPosting()
            ->identifier($post->publicUrl().'#article')
            ->headline(Str::limit((string) $post->title, 110, ''))
            ->description($post->summary())
            ->url($post->publicUrl())
            ->mainEntityOfPage($post->publicUrl())
            ->inLanguage(str_replace('_', '-', app()->getLocale()))
            ->datePublished($post->published_at)
            ->dateModified($post->updated_at)
            ->setProperty('publisher', $this->reference($this->organizationId()))
            ->setProperty('isPartOf', $this->reference($this->websiteId()));

        if ($post->author) {
            $article->author(Schema::person()->name($post->author->name));
        }

        if ($post->coverImageUrl()) {
            $article->image($post->coverImageUrl());
        }

        if ($post->category) {
            $article->articleSection((string) $post->category->name);
        }

        if ($post->tags->isNotEmpty()) {
            $article->keywords($post->tags->pluck('name')->implode(', '));
        }

        $graph->add($article);

        return $graph;
    }

    /**
     * @param  array<string, string>  $trail  name => absolute URL, after "Home"
     */
    public function breadcrumbs(array $trail): BreadcrumbList
    {
        $items = [];
        $position = 1;

        foreach ([__('marketing.home') => route('home')] + $trail as $name => $url) {
            $items[] = Schema::listItem()->position($position++)->name((string) $name)->setProperty('item', $url);
        }

        return Schema::breadcrumbList()->itemListElement($items);
    }

    protected function baseGraph(): Graph
    {
        $setting = $this->settings->current();
        $name = (string) ($setting->site_name ?: config('app.name'));

        $organization = Schema::organization()
            ->identifier($this->organizationId())
            ->name($name)
            ->url(route('home'))
            ->logo($this->absoluteUrl((string) config('seo.organization.logo')));

        if ($setting->support_email) {
            $organization->email($setting->support_email);
        }

        if ($sameAs = (array) config('seo.organization.same_as', [])) {
            $organization->sameAs($sameAs);
        }

        $website = Schema::webSite()
            ->identifier($this->websiteId())
            ->name($name)
            ->url(route('home'))
            ->inLanguage(str_replace('_', '-', app()->getLocale()))
            ->setProperty('publisher', $this->reference($this->organizationId()))
            ->potentialAction(
                Schema::searchAction()
                    ->target(route('blog').'?q={search_term_string}')
                    ->setProperty('query-input', 'required name=search_term_string')
            );

        if ($setting->site_description) {
            $website->description((string) $setting->site_description);
        }

        $graph = new Graph;
        $graph->add($organization)->add($website);

        return $graph;
    }

    /**
     * A JSON-LD node reference, so an entity is described once per graph.
     *
     * @return array{'@id': string}
     */
    protected function reference(string $id): array
    {
        return ['@id' => $id];
    }

    protected function organizationId(): string
    {
        return route('home').'#organization';
    }

    protected function websiteId(): string
    {
        return route('home').'#website';
    }

    protected function absoluteUrl(string $path): string
    {
        return Str::startsWith($path, ['http://', 'https://']) ? $path : asset($path);
    }
}

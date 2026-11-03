<?php

namespace App\Http\Controllers;

use App\Services\PostService;
use App\Services\StructuredDataService;
use App\Services\TagService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Public reader-facing blog: a listing of published posts (searchable and
 * filterable by tag) and a detail page.
 */
class BlogController extends Controller
{
    public function __construct(
        private readonly PostService $posts,
        private readonly TagService $tags,
        private readonly StructuredDataService $structuredData,
    ) {}

    public function index(Request $request): View
    {
        $search = $request->string('q')->trim()->toString() ?: null;
        $tagSlug = $request->string('tag')->trim()->toString() ?: null;

        // An unknown tag is a dead link, not an empty listing.
        $tag = $tagSlug ? ($this->tags->findBySlug($tagSlug) ?? abort(404)) : null;

        return view('blog', [
            'posts' => $this->posts->publishedPaginated($search, tag: $tag),
            'search' => $search,
            'tag' => $tag,
            'canonical' => $tag ? route('blog', ['tag' => $tagSlug]) : route('blog'),
            'structuredData' => $this->structuredData->forPage([__('marketing.blog') => route('blog')]),
        ]);
    }

    public function show(string $slug): View
    {
        $post = $this->posts->findPublishedBySlug($slug) ?? abort(404);

        return view('blog-post', [
            'post' => $post,
            'structuredData' => $this->structuredData->forPost($post),
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Services\CategoryService;
use App\Services\PostService;
use App\Services\SpreadsheetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin CSV/XLSX downloads. Files are streamed and never stored on the server.
 */
class ExportController extends Controller
{
    public function __construct(private readonly SpreadsheetService $spreadsheets) {}

    /**
     * Honours the same filters as the admin post list (q, status, category, tag).
     */
    public function posts(Request $request, string $format, PostService $posts): StreamedResponse
    {
        Gate::authorize('viewAny', Post::class);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:all,published,draft,trashed'],
            'category' => ['nullable', 'integer'],
            'tag' => ['nullable', 'integer'],
        ]);

        $status = $validated['status'] ?? 'all';

        $rows = $posts->exportRows($validated['q'] ?? null, [
            'status' => in_array($status, [Post::STATUS_DRAFT, Post::STATUS_PUBLISHED], true) ? $status : null,
            'trashed' => $status === 'trashed' ? 'only' : null,
            'category_id' => $validated['category'] ?? null,
            'tag' => $validated['tag'] ?? null,
        ]);

        return $this->spreadsheets->download($rows, 'posts', $format);
    }

    public function categories(string $format, CategoryService $categories): StreamedResponse
    {
        Gate::authorize('viewAny', Category::class);

        return $this->spreadsheets->download($categories->exportRows(), 'categories', $format);
    }
}

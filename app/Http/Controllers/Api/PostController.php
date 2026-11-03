<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\PostService;
use App\Services\TagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    /**
     * PostService::searchPaginated
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'in:'.implode(',', array_keys(Post::statuses()))],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'tag' => ['nullable', 'integer', 'exists:tags,id'],
            'trashed' => ['nullable', 'in:only,with'],
            'sort' => ['nullable', 'in:published_at,title,created_at'],
            'direction' => ['nullable', 'in:asc,desc'],
        ]);

        return response()->json(
            $this->posts->searchPaginated(
                $validated['search'] ?? null,
                $validated['per_page'] ?? 10,
                collect($validated)->only(['status', 'category_id', 'tag', 'trashed', 'sort', 'direction'])->all(),
            )
        );
    }

    /**
     * PostService::stats
     */
    public function stats(): JsonResponse
    {
        return response()->json($this->posts->stats());
    }

    /**
     * PostService::create
     */
    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', Post::class);

        $validated = $this->validatePost($request);

        $post = $this->posts->create([
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? [],
            'body' => $validated['body'],
            'slug' => $validated['slug'] ?? null,
            'status' => $validated['status'],
            'category_id' => $validated['category_id'] ?? null,
            'user_id' => $request->user()->id,
        ] + $this->tagsPayload($validated));

        return response()->json($post->load('tags'), 201);
    }

    /**
     * PostService::update
     */
    public function update(Request $request, Post $post): JsonResponse
    {
        Gate::authorize('update', $post);

        $validated = $this->validatePost($request, $post);

        $post = $this->posts->update($post, [
            'title' => $validated['title'],
            'excerpt' => $validated['excerpt'] ?? [],
            'body' => $validated['body'],
            'slug' => $validated['slug'] ?? null,
            'status' => $validated['status'],
            'category_id' => $validated['category_id'] ?? null,
        ] + $this->tagsPayload($validated));

        return response()->json($post->load('tags'));
    }

    /**
     * PostService::delete
     */
    public function destroy(Post $post): JsonResponse
    {
        Gate::authorize('delete', $post);

        $this->posts->delete($post);

        return response()->json(null, 204);
    }

    /**
     * PostService::restore (route binds trashed posts)
     */
    public function restore(Post $post): JsonResponse
    {
        Gate::authorize('restore', $post);

        return response()->json($this->posts->restore($post));
    }

    /**
     * PostService::forceDelete (route binds trashed posts)
     */
    public function forceDestroy(Post $post): JsonResponse
    {
        Gate::authorize('forceDelete', $post);

        $this->posts->forceDelete($post);

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePost(Request $request, ?Post $post = null): array
    {
        return $request->validate([
            'title' => ['required', 'array'],
            'title.en' => ['required', 'string', 'max:255'],
            'title.ar' => ['nullable', 'string', 'max:255'],
            'excerpt' => ['nullable', 'array'],
            'excerpt.*' => ['nullable', 'string'],
            'body' => ['required', 'array'],
            'body.en' => ['required', 'string'],
            'body.ar' => ['nullable', 'string'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'status' => ['required', Rule::in(array_keys(Post::statuses()))],
            'category_id' => ['nullable', 'exists:categories,id'],
            // Optional: tag names; when present they replace the post's tags.
            'tags' => ['sometimes', 'array', 'max:'.TagService::MAX_PER_MODEL],
            'tags.*' => ['string', 'max:'.TagService::MAX_LENGTH],
        ]);
    }

    /**
     * Only forwards "tags" when the client sent it, so omitting the key
     * leaves existing tags untouched.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function tagsPayload(array $validated): array
    {
        return array_key_exists('tags', $validated) ? ['tags' => $validated['tags']] : [];
    }
}

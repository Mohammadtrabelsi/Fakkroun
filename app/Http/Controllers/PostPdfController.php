<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\PdfBuilder;

/**
 * Admin download (or ?preview=1 inline view) of a post as PDF. Generated
 * on the fly and never stored, so there is no file to leak; access follows
 * PostPolicy::view, like the rest of the post screens.
 */
class PostPdfController extends Controller
{
    public function __invoke(Request $request, Post $post, PdfService $pdf): PdfBuilder
    {
        Gate::authorize('view', $post);

        $post->loadMissing(['author', 'category', 'tags']);

        $filename = $pdf->filename('post-'.$post->slug);
        $document = $pdf->document('pdf.post', ['post' => $post], $filename)
            ->headers(['Cache-Control' => 'private, no-store']);

        return $request->boolean('preview') ? $document->inline($filename) : $document->download($filename);
    }
}

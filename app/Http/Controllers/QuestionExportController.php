<?php

namespace App\Http\Controllers;

use App\Domain\Questions\Imports\QuestionExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class QuestionExportController extends Controller
{
    public function __invoke(Request $request, string $format, QuestionExportService $exports): StreamedResponse
    {
        return $exports->download($format, $request->only(['category_id', 'status']));
    }
}

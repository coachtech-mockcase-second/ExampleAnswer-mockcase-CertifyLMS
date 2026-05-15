<?php

namespace App\Http\Controllers;

use App\Http\Requests\SectionImage\StoreRequest;
use App\Models\Section;
use App\Models\SectionImage;
use App\UseCases\SectionImage\DestroyAction;
use App\UseCases\SectionImage\StoreAction;
use Illuminate\Http\JsonResponse;

class SectionImageController extends Controller
{
    public function store(Section $section, StoreRequest $request, StoreAction $action): JsonResponse
    {
        $image = $action($section, $request->user(), $request->file('file'));

        return response()->json([
            'id' => $image->id,
            'url' => '/storage/'.$image->path,
            'alt_placeholder' => pathinfo($image->original_filename, PATHINFO_FILENAME),
        ], 201);
    }

    public function destroy(SectionImage $image, DestroyAction $action): JsonResponse
    {
        $this->authorize('delete', $image);

        $action($image);

        return response()->json(null, 204);
    }
}

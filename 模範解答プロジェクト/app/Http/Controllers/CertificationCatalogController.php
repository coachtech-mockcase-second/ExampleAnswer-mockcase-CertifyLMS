<?php

namespace App\Http\Controllers;

use App\Http\Requests\CertificationCatalog\IndexRequest;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\UseCases\CertificationCatalog\IndexAction;
use App\UseCases\CertificationCatalog\ShowAction;
use Illuminate\View\View;

class CertificationCatalogController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $result = $action($request->user(), $validated);

        return view('certifications.index', [
            'catalog' => $result['catalog'],
            'enrolled' => $result['enrolled'],
            'enrolledIds' => $result['enrolled_ids'],
            'categories' => CertificationCategory::ordered()->get(),
            'tab' => $validated['tab'] ?? 'catalog',
            'categoryId' => $validated['category_id'] ?? '',
            'difficulty' => $validated['difficulty'] ?? '',
        ]);
    }

    public function show(Certification $certification, ShowAction $action): View
    {
        $this->authorize('view', $certification);

        return view('certifications.show', [
            'certification' => $action($certification),
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CertificationCatalog\IndexRequest;
use App\Models\Certification;
use App\Models\CertificationCategory;
use App\UseCases\CertificationCatalog\IndexAction;
use App\UseCases\CertificationCatalog\ShowAction;
use Illuminate\View\View;

/**
 * 受講生向けの資格カタログ Controller。一覧と詳細を提供する。
 * `auth + role:student + active-learning` Middleware 配下で動作し、graduated 受講生はアクセス不可。
 */
class CertificationCatalogController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();

        $result = $action($request->user(), $validated);

        return view('certifications.index', [
            'catalog' => $result['catalog'],
            'enrolledIds' => $result['enrolled_ids'],
            'categories' => CertificationCategory::ordered()->get(),
            'categoryId' => $validated['category_id'] ?? '',
            'difficulty' => $validated['difficulty'] ?? '',
        ]);
    }

    public function show(Certification $certification, ShowAction $action): View
    {
        $this->authorize('view', $certification);

        $isEnrolled = request()->user()
            ->enrollments()
            ->where('certification_id', $certification->id)
            ->exists();

        return view('certifications.show', [
            'certification' => $action($certification),
            'isEnrolled' => $isEnrolled,
        ]);
    }
}

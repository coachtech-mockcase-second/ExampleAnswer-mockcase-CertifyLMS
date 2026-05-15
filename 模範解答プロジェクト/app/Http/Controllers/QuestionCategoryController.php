<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuestionCategory\StoreRequest;
use App\Http\Requests\QuestionCategory\UpdateRequest;
use App\Models\Certification;
use App\Models\QuestionCategory;
use App\UseCases\QuestionCategory\DestroyAction;
use App\UseCases\QuestionCategory\IndexAction;
use App\UseCases\QuestionCategory\StoreAction;
use App\UseCases\QuestionCategory\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class QuestionCategoryController extends Controller
{
    public function index(Certification $certification, IndexAction $action): View
    {
        $this->authorize('viewAny', [QuestionCategory::class, $certification]);

        return view('admin.contents.question-categories.index', [
            'certification' => $certification,
            'categories' => $action($certification),
        ]);
    }

    public function store(Certification $certification, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($certification, $request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $certification)
            ->with('success', 'カテゴリを作成しました。');
    }

    public function update(QuestionCategory $category, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($category, $request->user(), $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $category->certification_id)
            ->with('success', 'カテゴリを更新しました。');
    }

    public function destroy(QuestionCategory $category, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $category);

        $certificationId = $category->certification_id;
        $action($category);

        return redirect()
            ->route('admin.certifications.question-categories.index', $certificationId)
            ->with('success', 'カテゴリを削除しました。');
    }
}

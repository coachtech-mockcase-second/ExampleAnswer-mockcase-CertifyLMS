<?php

declare(strict_types=1);

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

/**
 * 出題分野マスタ管理 Controller。
 * 演習問題(SectionQuestion) と模試問題(MockExamQuestion) の両系統から参照される共有マスタの CRUD を担う。
 */
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
        $action($certification, $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $certification)
            ->with('success', '出題分野を作成しました。');
    }

    public function update(QuestionCategory $category, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($category, $request->validated());

        return redirect()
            ->route('admin.certifications.question-categories.index', $category->certification_id)
            ->with('success', '出題分野を更新しました。');
    }

    public function destroy(QuestionCategory $category, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $category);

        $certificationId = $category->certification_id;
        $action($category);

        return redirect()
            ->route('admin.certifications.question-categories.index', $certificationId)
            ->with('success', '出題分野を削除しました。');
    }
}

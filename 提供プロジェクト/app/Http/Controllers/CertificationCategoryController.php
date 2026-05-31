<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\CertificationCategory\StoreRequest;
use App\Http\Requests\CertificationCategory\UpdateRequest;
use App\Models\CertificationCategory;
use App\UseCases\CertificationCategory\DestroyAction;
use App\UseCases\CertificationCategory\IndexAction;
use App\UseCases\CertificationCategory\StoreAction;
use App\UseCases\CertificationCategory\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin 用の資格分類マスタ管理画面 Controller。モーダル UI からの CRUD を提供する。
 */
class CertificationCategoryController extends Controller
{
    public function index(IndexAction $action): View
    {
        $this->authorize('viewAny', CertificationCategory::class);

        return view('certification-category.management.index', [
            'categories' => $action(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->validated());

        return redirect()
            ->route('admin.certification-categories.index')
            ->with('success', '分類を追加しました。');
    }

    public function update(CertificationCategory $category, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($category, $request->validated());

        return redirect()
            ->route('admin.certification-categories.index')
            ->with('success', '分類を更新しました。');
    }

    public function destroy(CertificationCategory $category, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $category);

        $action($category);

        return redirect()
            ->route('admin.certification-categories.index')
            ->with('success', '分類を削除しました。');
    }
}

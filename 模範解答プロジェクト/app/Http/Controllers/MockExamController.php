<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MockExam\IndexRequest;
use App\Http\Requests\MockExam\ReorderRequest;
use App\Http\Requests\MockExam\StoreRequest;
use App\Http\Requests\MockExam\UpdateRequest;
use App\Models\Certification;
use App\Models\MockExam;
use App\UseCases\MockExam\DestroyAction;
use App\UseCases\MockExam\IndexAction;
use App\UseCases\MockExam\PublishAction;
use App\UseCases\MockExam\ReorderAction;
use App\UseCases\MockExam\ShowAction;
use App\UseCases\MockExam\StoreAction;
use App\UseCases\MockExam\UnpublishAction;
use App\UseCases\MockExam\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin / coach 用の模試マスタ管理画面 Controller。CRUD + 公開状態遷移 + 並び順を提供する。
 */
class MockExamController extends Controller
{
    public function index(IndexRequest $request, IndexAction $action): View
    {
        $validated = $request->validated();
        $isPublished = isset($validated['is_published'])
            ? in_array($validated['is_published'], ['1', 'true', true], true)
            : null;

        $mockExams = $action(
            auth: $request->user(),
            keyword: $validated['keyword'] ?? null,
            certificationId: $validated['certification_id'] ?? null,
            isPublished: $isPublished,
        );

        return view('admin.mock-exams.index', [
            'mockExams' => $mockExams,
            'certifications' => Certification::query()->orderBy('name')->get(),
            'keyword' => $validated['keyword'] ?? '',
            'certificationId' => $validated['certification_id'] ?? '',
            'isPublished' => $validated['is_published'] ?? '',
        ]);
    }

    public function show(MockExam $mockExam, ShowAction $action): View
    {
        $this->authorize('view', $mockExam);

        return view('admin.mock-exams.show', [
            'mockExam' => $action($mockExam),
        ]);
    }

    public function create(): View
    {
        $this->authorize('viewAny', MockExam::class);

        return view('admin.mock-exams.create', [
            'certifications' => Certification::query()->orderBy('name')->get(),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $mockExam = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試マスタを作成しました。');
    }

    public function edit(MockExam $mockExam): View
    {
        $this->authorize('update', $mockExam);

        return view('admin.mock-exams.edit', [
            'mockExam' => $mockExam,
        ]);
    }

    public function update(MockExam $mockExam, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($mockExam, $request->user(), $request->validated());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試マスタを更新しました。');
    }

    public function destroy(MockExam $mockExam, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $mockExam);

        $action($mockExam);

        return redirect()
            ->route('admin.mock-exams.index')
            ->with('success', '模試マスタを削除しました。');
    }

    public function publish(MockExam $mockExam, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $mockExam);

        $action($mockExam, request()->user());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試を公開しました。');
    }

    public function unpublish(MockExam $mockExam, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $mockExam);

        $action($mockExam, request()->user());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試の公開を停止しました。');
    }

    public function reorder(ReorderRequest $request, ReorderAction $action): RedirectResponse
    {
        $validated = $request->validated();
        $action($validated['certification_id'], $validated['items']);

        return redirect()
            ->route('admin.mock-exams.index', ['certification_id' => $validated['certification_id']])
            ->with('success', '並び順を更新しました。');
    }
}

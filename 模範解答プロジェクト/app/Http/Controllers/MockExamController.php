<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MockExam\IndexRequest;
use App\Http\Requests\MockExam\StoreRequest;
use App\Http\Requests\MockExam\UpdateRequest;
use App\Models\Certification;
use App\Models\MockExam;
use App\UseCases\MockExam\DestroyAction;
use App\UseCases\MockExam\IndexAction;
use App\UseCases\MockExam\PublishAction;
use App\UseCases\MockExam\ShowAction;
use App\UseCases\MockExam\StoreAction;
use App\UseCases\MockExam\UnpublishAction;
use App\UseCases\MockExam\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * admin / coach 用の模試マスタ管理画面 Controller。CRUD + 公開状態遷移を提供する。
 */
class MockExamController extends Controller
{
    /**
     * 模試マスタを名前キーワード・資格・公開状態で絞り込んで一覧表示する。
     */
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

        return view('mock-exam.management.index', [
            'mockExams' => $mockExams,
            'certifications' => Certification::query()->orderBy('name')->get(),
            'keyword' => $validated['keyword'] ?? '',
            'certificationId' => $validated['certification_id'] ?? '',
            'isPublished' => $validated['is_published'] ?? '',
        ]);
    }

    /**
     * 模試マスタの詳細を表示する。
     */
    public function show(MockExam $mockExam, ShowAction $action): View
    {
        $this->authorize('view', $mockExam);

        return view('mock-exam.management.show', [
            'mockExam' => $action($mockExam),
        ]);
    }

    /**
     * 模試マスタの新規作成フォームを表示する。
     */
    public function create(): View
    {
        $this->authorize('viewAny', MockExam::class);

        return view('mock-exam.management.create', [
            'certifications' => Certification::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * 模試マスタを新規作成し、作成した詳細画面へリダイレクトする。
     */
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $mockExam = $action($request->user(), $request->validated());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試マスタを作成しました。');
    }

    /**
     * 模試マスタの編集フォームを表示する。
     */
    public function edit(MockExam $mockExam): View
    {
        $this->authorize('update', $mockExam);

        return view('mock-exam.management.edit', [
            'mockExam' => $mockExam,
        ]);
    }

    /**
     * 模試マスタの内容を更新し、詳細画面へリダイレクトする。
     */
    public function update(MockExam $mockExam, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($mockExam, $request->user(), $request->validated());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試マスタを更新しました。');
    }

    /**
     * 模試マスタを削除し、一覧画面へリダイレクトする。
     */
    public function destroy(MockExam $mockExam, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $mockExam);

        $action($mockExam);

        return redirect()
            ->route('admin.mock-exams.index')
            ->with('success', '模試マスタを削除しました。');
    }

    /**
     * 模試を公開状態に切り替え、詳細画面へリダイレクトする。
     */
    public function publish(MockExam $mockExam, PublishAction $action): RedirectResponse
    {
        $this->authorize('publish', $mockExam);

        $action($mockExam, request()->user());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試を公開しました。');
    }

    /**
     * 模試を非公開状態に切り替え、詳細画面へリダイレクトする。
     */
    public function unpublish(MockExam $mockExam, UnpublishAction $action): RedirectResponse
    {
        $this->authorize('unpublish', $mockExam);

        $action($mockExam, request()->user());

        return redirect()
            ->route('admin.mock-exams.show', $mockExam)
            ->with('success', '模試の公開を停止しました。');
    }
}

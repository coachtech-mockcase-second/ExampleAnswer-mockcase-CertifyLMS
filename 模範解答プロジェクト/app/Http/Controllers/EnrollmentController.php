<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Enrollment\StoreRequest;
use App\Models\Enrollment;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ResumeAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生向け受講登録 Controller。受講中一覧 / 詳細 / 自己登録 / 受講解除 / failed からの再挑戦を提供する。
 */
class EnrollmentController extends Controller
{
    public function index(IndexAction $action): View
    {
        $enrollments = $action(auth()->user());

        return view('enrollments.index', [
            'enrollments' => $enrollments,
        ]);
    }

    public function show(Enrollment $enrollment, ShowAction $action): View
    {
        $this->authorize('view', $enrollment);

        return view('enrollments.show', [
            'enrollment' => $action($enrollment),
        ]);
    }

    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $enrollment = $action(auth()->user(), $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '受講登録が完了しました。');
    }

    public function destroy(Enrollment $enrollment, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $enrollment);

        $action($enrollment);

        return redirect()
            ->route('enrollments.index')
            ->with('success', '受講登録を解除しました。');
    }

    public function resume(Enrollment $enrollment, ResumeAction $action): RedirectResponse
    {
        $this->authorize('resume', $enrollment);

        $action($enrollment, auth()->user());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '学習を再開しました。');
    }
}

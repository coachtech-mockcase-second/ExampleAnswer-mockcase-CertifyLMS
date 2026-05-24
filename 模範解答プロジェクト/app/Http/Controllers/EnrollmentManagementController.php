<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Enrollment\FailRequest;
use App\Http\Requests\Enrollment\UpdateExamDateRequest;
use App\Models\Enrollment;
use App\UseCases\Enrollment\FailAction;
use App\UseCases\Enrollment\UpdateExamDateAction;
use Illuminate\Http\RedirectResponse;

/**
 * 管理者向け受講登録の admin 固有業務操作 Controller。
 *
 * - updateExamDate: 受講生に代わって目標受験日を変更
 * - fail: 受講生を学習中止(failed)状態へ手動遷移
 *
 * 一覧 / 詳細は `EnrollmentController` の 3 ロール共有エンドポイント(`enrollments.index` / `enrollments.show`)に
 * 集約済。本 Controller は admin 専用業務(状態変更 + 履歴記録)のみを担う。受講登録の新規作成は受講生自身の
 * 自己登録のみで完結し、admin による手動割当は提供しない。
 */
class EnrollmentManagementController extends Controller
{
    public function updateExamDate(
        Enrollment $enrollment,
        UpdateExamDateRequest $request,
        UpdateExamDateAction $action,
    ): RedirectResponse {
        $action($enrollment, $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '目標受験日を更新しました。');
    }

    public function fail(
        Enrollment $enrollment,
        FailRequest $request,
        FailAction $action,
    ): RedirectResponse {
        $action($enrollment, auth()->user(), $request->validated('reason'));

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '受講登録を学習中止にしました。');
    }
}

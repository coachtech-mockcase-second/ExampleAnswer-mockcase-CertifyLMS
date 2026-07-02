<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EnrollmentGoal\StoreRequest;
use App\Http\Requests\EnrollmentGoal\UpdateRequest;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\UseCases\EnrollmentGoal\DestroyAction;
use App\UseCases\EnrollmentGoal\MarkAchievedAction;
use App\UseCases\EnrollmentGoal\StoreAction;
use App\UseCases\EnrollmentGoal\UnmarkAchievedAction;
use App\UseCases\EnrollmentGoal\UpdateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * 受講生本人による個人目標 Controller。コーチ / admin は閲覧専用なので CRUD エンドポイントは持たない。
 */
class EnrollmentGoalController extends Controller
{
    /**
     * 個人目標を追加し、受講登録詳細へリダイレクトする。
     */
    public function store(Enrollment $enrollment, StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($enrollment, $request->validated());

        return redirect()
            ->route('enrollments.show', $enrollment)
            ->with('success', '目標を追加しました。');
    }

    /**
     * 個人目標の編集フォームを表示する。
     */
    public function edit(EnrollmentGoal $goal): View
    {
        $this->authorize('update', $goal);

        return view('enrollment-goal.edit', [
            'goal' => $goal,
        ]);
    }

    /**
     * 個人目標のタイトル・詳細・目標期日を更新する (達成日時は変更しない)。
     */
    public function update(EnrollmentGoal $goal, UpdateRequest $request, UpdateAction $action): RedirectResponse
    {
        $action($goal, $request->validated());

        return redirect()
            ->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '目標を更新しました。');
    }

    /**
     * 個人目標を物理削除する。
     */
    public function destroy(EnrollmentGoal $goal, DestroyAction $action): RedirectResponse
    {
        $this->authorize('delete', $goal);

        $enrollmentId = $goal->enrollment_id;
        $action($goal);

        return redirect()
            ->route('enrollments.show', $enrollmentId)
            ->with('success', '目標を削除しました。');
    }

    /**
     * 個人目標に達成マークを付け、達成日時を現在時刻で記録する (既達成でも冪等)。
     */
    public function markAchieved(EnrollmentGoal $goal, MarkAchievedAction $action): RedirectResponse
    {
        $this->authorize('markAchieved', $goal);

        $action($goal);

        return redirect()
            ->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '目標を達成済にしました。');
    }

    /**
     * 個人目標の達成マークを解除し、達成日時を NULL に戻す (未達成でも冪等)。
     */
    public function unmarkAchieved(EnrollmentGoal $goal, UnmarkAchievedAction $action): RedirectResponse
    {
        $this->authorize('unmarkAchieved', $goal);

        $action($goal);

        return redirect()
            ->route('enrollments.show', $goal->enrollment_id)
            ->with('success', '目標を未達成に戻しました。');
    }
}

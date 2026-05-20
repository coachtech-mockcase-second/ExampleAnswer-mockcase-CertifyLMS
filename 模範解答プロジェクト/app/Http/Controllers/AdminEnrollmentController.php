<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Http\Requests\Enrollment\FailRequest;
use App\Http\Requests\Enrollment\UpdateExamDateRequest;
use App\Models\Certification;
use App\Models\Enrollment;
use App\UseCases\Enrollment\FailAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\UpdateExamDateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * 管理者向け受講登録 Controller。全件一覧 / 詳細 / 試験日変更 / 手動失敗マークを提供する。
 *
 * 一覧フィルタ: status / certification_id / キーワード(受講生名 / メール 部分一致)。
 * withTrashed クエリパラメータで論理削除済の Enrollment も含めて返す。
 *
 * 受講登録の新規作成は受講生自身の自己登録のみで完結し、admin による手動割当は提供しない。
 */
class AdminEnrollmentController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAdmin', Enrollment::class);

        $query = Enrollment::query()
            ->with(['user', 'certification.category', 'latestStatusLog']);

        if ($request->boolean('with_trashed')) {
            $query->withTrashed();
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', EnrollmentStatus::from($status)->value);
        }

        if ($certificationId = $request->string('certification_id')->toString()) {
            $query->where('certification_id', $certificationId);
        }

        if ($keyword = trim($request->string('keyword')->toString())) {
            $query->whereHas(
                'user',
                fn ($q) => $q
                    ->where('name', 'LIKE', '%'.$keyword.'%')
                    ->orWhere('email', 'LIKE', '%'.$keyword.'%'),
            );
        }

        $enrollments = $query
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.enrollments.index', [
            'enrollments' => $enrollments,
            'status' => $request->string('status')->toString(),
            'certification_id' => $request->string('certification_id')->toString(),
            'keyword' => $request->string('keyword')->toString(),
            'withTrashed' => $request->boolean('with_trashed'),
            'certifications' => Certification::query()->orderBy('name')->get(),
        ]);
    }

    public function show(Enrollment $enrollment, ShowAction $action): View
    {
        $this->authorize('view', $enrollment);

        return view('admin.enrollments.show', [
            'enrollment' => $action($enrollment->loadMissing('user')),
        ]);
    }

    public function updateExamDate(
        Enrollment $enrollment,
        UpdateExamDateRequest $request,
        UpdateExamDateAction $action,
    ): RedirectResponse {
        $action($enrollment, $request->validated());

        return redirect()
            ->route('admin.enrollments.show', $enrollment)
            ->with('success', '目標受験日を更新しました。');
    }

    public function fail(
        Enrollment $enrollment,
        FailRequest $request,
        FailAction $action,
    ): RedirectResponse {
        $action($enrollment, auth()->user(), $request->validated('reason'));

        return redirect()
            ->route('admin.enrollments.show', $enrollment)
            ->with('success', '受講登録を学習中止にしました。');
    }
}

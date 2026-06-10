<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Http\Requests\Enrollment\StoreRequest;
use App\Http\Requests\Enrollment\UpdateExamDateRequest;
use App\Models\Certification;
use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Services\Learning\ProgressSummary;
use App\UseCases\Enrollment\DestroyAction;
use App\UseCases\Enrollment\IndexAction;
use App\UseCases\Enrollment\ResumeAction;
use App\UseCases\Enrollment\ShowAction;
use App\UseCases\Enrollment\StoreAction;
use App\UseCases\Enrollment\UpdateExamDateAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * 受講登録 Controller。3 ロール共通の閲覧導線(index / show)を提供する。
 *
 * - student: 自分の受講登録 一覧 / 詳細 + 自己登録 / 受講解除 / failed からの再挑戦 + 目標受験日の設定
 * - coach: 担当資格に登録された受講生の一覧 / 詳細(進捗カード + コーチメモ + 個人目標閲覧)
 * - admin: 全件 + フィルタ + paginate / 詳細(状態遷移ログ + 試験日変更 + 学習中止リンク)
 *
 * 表示要素のロール差は Blade の `@can` / `auth()->user()->role` 判定で出し分ける。
 * admin 専用の業務操作(試験日変更 / 学習中止)は `EnrollmentManagementController` に分離している。
 */
class EnrollmentController extends Controller
{
    public function index(Request $request, IndexAction $action): View
    {
        $this->authorize('viewAny', Enrollment::class);

        $user = auth()->user();

        // 受講生本人: 自分の受講登録一覧(進捗カード UI)
        if ($user->role === UserRole::Student) {
            return view('enrollment.index', [
                'enrollments' => $action($user),
            ]);
        }

        // staff(admin / coach): 管理視点の一覧(テーブル + フィルタ + paginate)
        $query = Enrollment::query()
            ->forUser($user)
            ->with(['user', 'certification.category', 'latestStatusLog']);

        if ($user->role === UserRole::Admin && $request->boolean('with_trashed')) {
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

        // フィルタ用資格選択肢: admin = 全資格 / coach = 担当資格のみ
        $certifications = $user->role === UserRole::Admin
            ? Certification::query()->orderBy('name')->get()
            : $user->assignedCertifications()->orderBy('name')->get();

        return view('enrollment.index', [
            'enrollments' => $enrollments,
            'status' => $request->string('status')->toString(),
            'certification_id' => $request->string('certification_id')->toString(),
            'keyword' => $request->string('keyword')->toString(),
            'withTrashed' => $request->boolean('with_trashed'),
            'certifications' => $certifications,
        ]);
    }

    public function show(
        Enrollment $enrollment,
        ShowAction $action,
    ): View {
        $this->authorize('view', $enrollment);

        $action($enrollment);

        $user = auth()->user();
        $progress = null;

        // staff(admin / coach)時のみ進捗集計とコーチメモを eager-load
        if (in_array($user->role, [UserRole::Coach, UserRole::Admin], true)) {
            $enrollment->loadMissing(['user']);
            $progress = $this->summarizeProgress($enrollment);
        }

        // admin 時のみ状態遷移ログを eager-load(管理画面の監査ビュー)
        if ($user->role === UserRole::Admin) {
            $enrollment->loadMissing([
                'statusLogs' => fn ($q) => $q->with('changedBy')->orderByDesc('changed_at'),
            ]);
        }

        return view('enrollment.show', [
            'enrollment' => $enrollment,
            'progress' => $progress,
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

    /**
     * 受講生本人による目標受験日の設定 / 変更。認可は UpdateExamDateRequest が
     * EnrollmentPolicy::updateExamDate (admin or 本人、status != Passed) に委譲する。
     */
    public function updateExamDate(
        Enrollment $enrollment,
        UpdateExamDateRequest $request,
        UpdateExamDateAction $action,
    ): RedirectResponse {
        $action($enrollment, $request->validated());

        return redirect()
            ->route('dashboard.index')
            ->with('success', '目標受験日を更新しました。');
    }

    /**
     * 学習進捗 (Section→Chapter→Part→資格 完了率) の 4 階層サマリを算出する。
     */
    private function summarizeProgress(Enrollment $enrollment): ProgressSummary
    {
        $totals = $this->fetchSectionTotals($enrollment);

        $partsTotal = Part::query()
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->count();

        $chaptersTotal = Chapter::query()
            ->whereHas('part', function ($q) use ($enrollment) {
                $q->where('certification_id', $enrollment->certification_id)
                    ->where('status', ContentStatus::Published->value);
            })
            ->where('status', ContentStatus::Published->value)
            ->count();

        $sectionsTotal = (int) $totals->sections_total;
        $sectionsCompleted = (int) $totals->sections_completed;
        $sectionRatio = $sectionsTotal === 0 ? 0.0 : round($sectionsCompleted / $sectionsTotal, 4);

        $chaptersCompleted = $this->countCompletedChapters($enrollment);
        $partsCompleted = $this->countCompletedParts($enrollment);

        $chapterRatio = $chaptersTotal === 0 ? 0.0 : round($chaptersCompleted / $chaptersTotal, 4);
        $partRatio = $partsTotal === 0 ? 0.0 : round($partsCompleted / $partsTotal, 4);

        return new ProgressSummary(
            sectionsTotal: $sectionsTotal,
            sectionsCompleted: $sectionsCompleted,
            sectionCompletionRatio: $sectionRatio,
            chaptersTotal: $chaptersTotal,
            chaptersCompleted: $chaptersCompleted,
            chapterCompletionRatio: $chapterRatio,
            partsTotal: $partsTotal,
            partsCompleted: $partsCompleted,
            partCompletionRatio: $partRatio,
            overallCompletionRatio: $sectionRatio,
        );
    }

    private function fetchSectionTotals(Enrollment $enrollment): object
    {
        return DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->selectRaw('COUNT(sections.id) AS sections_total, COUNT(section_progresses.id) AS sections_completed')
            ->first() ?? (object) ['sections_total' => 0, 'sections_completed' => 0];
    }

    private function countCompletedChapters(Enrollment $enrollment): int
    {
        // 公開済 Chapter のうち、配下の公開済 Section が全て読了済かを Chapter 単位で判定。
        $rows = DB::table('chapters')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->groupBy('chapters.id')
            ->selectRaw('chapters.id AS chapter_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }

    private function countCompletedParts(Enrollment $enrollment): int
    {
        $rows = DB::table('parts')
            ->leftJoin('chapters', function ($join) {
                $join->on('chapters.part_id', '=', 'parts.id')
                    ->where('chapters.status', ContentStatus::Published->value);
            })
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->groupBy('parts.id')
            ->selectRaw('parts.id AS part_id, COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')
            ->get();

        $completed = 0;
        foreach ($rows as $row) {
            if ((int) $row->total > 0 && (int) $row->total === (int) $row->done) {
                $completed++;
            }
        }

        return $completed;
    }
}

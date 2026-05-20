<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Services\ProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * コーチ向け担当資格受講生管理 Controller。
 *
 * - index: 自分が担当として割り当てられた資格 (`certification_coach_assignments`) に属する Enrollment の一覧
 * - show: 単一 Enrollment 詳細(受講生情報 / 進捗 / 目標 / 担当コーチメモ)
 *
 * 認可は `EnrollmentPolicy::viewAny` (admin / coach / student いずれも許可) と
 * `Enrollment::scopeForUser($coach)` の組み合わせで実現する。個別レコードへの認可は `EnrollmentPolicy::view`
 * で `isAssignedCoach` 判定。
 */
class EnrollmentRosterController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Enrollment::class);

        $coach = $request->user();

        $query = Enrollment::query()
            ->forUser($coach)
            ->with(['user', 'certification.category', 'latestStatusLog']);

        if ($certificationId = $request->string('certification_id')->toString()) {
            $query->where('certification_id', $certificationId);
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', EnrollmentStatus::from($status)->value);
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

        return view('enrollment.coach.index', [
            'enrollments' => $enrollments,
            'certifications' => $coach->assignedCertifications()->orderBy('name')->get(),
            'status' => $request->string('status')->toString(),
            'certification_id' => $request->string('certification_id')->toString(),
            'keyword' => $request->string('keyword')->toString(),
        ]);
    }

    public function show(Enrollment $enrollment, ProgressService $progressService): View
    {
        $this->authorize('view', $enrollment);

        $enrollment->loadMissing([
            'user',
            'certification.category',
            'goals' => fn ($q) => $q->orderByDesc('created_at'),
            'notes' => fn ($q) => $q->with('coach')->latest(),
        ]);

        return view('enrollment.coach.show', [
            'enrollment' => $enrollment,
            'progress' => $progressService->summarize($enrollment),
        ]);
    }
}

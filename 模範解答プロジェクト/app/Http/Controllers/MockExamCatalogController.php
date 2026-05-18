<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\UseCases\MockExamCatalog\IndexAction;
use App\UseCases\MockExamCatalog\ShowAction;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Illuminate\View\View;

/**
 * 受講生視点の模試カタログ Controller。
 *
 * `/learning/enrollments/{enrollment}/mock-exams` 配下に配置され、受講中 / 修了済の資格に紐づく公開模試を閲覧する。
 */
class MockExamCatalogController extends Controller
{
    public function index(Enrollment $enrollment, IndexAction $action): View
    {
        $this->ensureEnrollmentBelongsToStudent($enrollment);

        $mockExams = $action($enrollment);
        $activeSessions = $action->activeSessionMap($enrollment);

        return view('mock-exams.index', [
            'enrollment' => $enrollment->load('certification'),
            'mockExams' => $mockExams,
            'activeSessions' => $activeSessions,
        ]);
    }

    public function show(Enrollment $enrollment, MockExam $mockExam, ShowAction $action): View
    {
        $this->ensureEnrollmentBelongsToStudent($enrollment);

        if ($mockExam->certification_id !== $enrollment->certification_id) {
            throw new NotFoundHttpException;
        }

        $this->authorize('take', $mockExam);

        return view('mock-exams.show', [
            'enrollment' => $enrollment->load('certification'),
            'mockExam' => $action($mockExam, $enrollment),
            'activeSession' => $action->findActiveSession($mockExam, $enrollment),
        ]);
    }

    private function ensureEnrollmentBelongsToStudent(Enrollment $enrollment): void
    {
        $user = request()->user();
        if ($user === null || $enrollment->user_id !== $user->id) {
            throw new AccessDeniedHttpException('この受講登録にアクセスする権限がありません。');
        }

        if (! in_array($enrollment->status, [EnrollmentStatus::Learning, EnrollmentStatus::Passed], true)) {
            throw new AccessDeniedHttpException('受講中または修了済の資格のみ模試を表示できます。');
        }
    }
}

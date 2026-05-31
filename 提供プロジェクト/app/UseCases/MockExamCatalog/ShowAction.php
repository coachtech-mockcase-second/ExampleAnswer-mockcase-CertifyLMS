<?php

declare(strict_types=1);

namespace App\UseCases\MockExamCatalog;

use App\Enums\MockExamSessionStatus;
use App\Models\Enrollment;
use App\Models\MockExam;
use App\Models\MockExamSession;

/**
 * 受講生視点の模試詳細を取得するユースケース。
 *
 * 進行中(NotStarted / InProgress) のセッションがあれば再開導線用にロード、なければ新規セッション作成導線を表示する。
 */
final class ShowAction
{
    public function __invoke(MockExam $mockExam, Enrollment $enrollment): MockExam
    {
        return $mockExam
            ->loadCount('mockExamQuestions')
            ->load('certification');
    }

    public function findActiveSession(MockExam $mockExam, Enrollment $enrollment): ?MockExamSession
    {
        return MockExamSession::query()
            ->where('mock_exam_id', $mockExam->id)
            ->where('enrollment_id', $enrollment->id)
            ->whereIn('status', [
                MockExamSessionStatus::NotStarted->value,
                MockExamSessionStatus::InProgress->value,
            ])
            ->orderByDesc('created_at')
            ->first();
    }
}

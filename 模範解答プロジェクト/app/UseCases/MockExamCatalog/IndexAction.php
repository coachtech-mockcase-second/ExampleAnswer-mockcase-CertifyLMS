<?php

declare(strict_types=1);

namespace App\UseCases\MockExamCatalog;

use App\Enums\MockExamSessionStatus;
use App\Models\Enrollment;
use App\Models\MockExam;
use Illuminate\Database\Eloquent\Collection;

/**
 * 受講生 1 名 × 受講中(learning + passed) 資格 1 件に紐づく公開模試一覧を取得するユースケース。
 *
 * 各模試について以下の情報を併記:
 *   - questions_count: 問題数
 *   - latestSession: 最新セッション(履歴用)
 *   - activeSession: 進行中(NotStarted / InProgress / Submitted) セッション(再開導線用)
 *   - bestSession: 受講登録単位での最高得点 graded セッション(合格判定の根拠)
 */
final class IndexAction
{
    /**
     * @return Collection<int, MockExam>
     */
    public function __invoke(Enrollment $enrollment): Collection
    {
        return MockExam::query()
            ->published()
            ->forCertification($enrollment->certification_id)
            ->withCount('mockExamQuestions')
            ->with([
                'sessions' => fn ($q) => $q->where('enrollment_id', $enrollment->id)
                    ->orderByDesc('created_at'),
            ])
            ->orderBy('order')
            ->get();
    }

    /**
     * 当該 Enrollment に紐づく進行中セッション(NotStarted / InProgress / Submitted)を MockExam 単位で連想配列化する。
     *
     * @return array<string, string>  mock_exam_id => session_id
     */
    public function activeSessionMap(Enrollment $enrollment): array
    {
        return $enrollment->mockExamSessions()
            ->whereIn('status', [
                MockExamSessionStatus::NotStarted->value,
                MockExamSessionStatus::InProgress->value,
                MockExamSessionStatus::Submitted->value,
            ])
            ->pluck('id', 'mock_exam_id')
            ->all();
    }
}

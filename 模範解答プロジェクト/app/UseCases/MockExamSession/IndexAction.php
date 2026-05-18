<?php

declare(strict_types=1);

namespace App\UseCases\MockExamSession;

use App\Enums\MockExamSessionStatus;
use App\Models\MockExamSession;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 受講生本人の模試受験履歴一覧を取得するユースケース。
 *
 * graded / canceled のみ表示する(in_progress / submitted は dashboard 等の別動線から扱う)。
 * フィルタ: certification_id / mock_exam_id / pass
 */
final class IndexAction
{
    public function __invoke(
        User $student,
        ?string $certificationId = null,
        ?string $mockExamId = null,
        ?bool $pass = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        $query = MockExamSession::query()
            ->forUser($student)
            ->whereIn('status', [
                MockExamSessionStatus::Graded->value,
                MockExamSessionStatus::Canceled->value,
            ])
            ->with(['mockExam.certification']);

        if ($certificationId !== null && $certificationId !== '') {
            $query->whereHas('mockExam', fn ($q) => $q->where('certification_id', $certificationId));
        }

        if ($mockExamId !== null && $mockExamId !== '') {
            $query->where('mock_exam_id', $mockExamId);
        }

        if ($pass !== null) {
            $query->where('pass', $pass);
        }

        return $query
            ->orderByDesc('graded_at')
            ->orderByDesc('canceled_at')
            ->paginate($perPage)
            ->withQueryString();
    }
}

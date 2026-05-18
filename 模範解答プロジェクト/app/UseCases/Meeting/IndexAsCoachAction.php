<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * コーチ視点の面談一覧(自分宛の Meeting)を filter / student / enrollment 絞り込みで paginate するユースケース。
 *
 * eager load は coach ダッシュボードと履歴画面で必要な enrollment.certification / student を先読みする。
 *
 * @see \App\Http\Controllers\MeetingController::indexAsCoach()
 */
final class IndexAsCoachAction
{
    /**
     * @param  array{filter?: ?string, student?: ?string, enrollment?: ?string}  $filters
     * @return LengthAwarePaginator<Meeting>
     */
    public function __invoke(User $coach, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $filter = $filters['filter'] ?? 'upcoming';
        $studentId = $filters['student'] ?? null;
        $enrollmentId = $filters['enrollment'] ?? null;

        $query = Meeting::query()
            ->with(['enrollment.certification', 'student'])
            ->forCoach($coach)
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        // upcoming: 次の面談を一番上に置く (昇順) / past + all: 直近の活動を一番上 (降順)
        return match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate($perPage),
            'all' => $query->orderByDesc('scheduled_at')->paginate($perPage),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate($perPage),
        };
    }
}

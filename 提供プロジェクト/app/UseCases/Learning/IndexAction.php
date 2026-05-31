<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\EnrollmentStatus;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * 教材ブラウジング 1 階層目 (/learning) のフォールバック UI 用データを準備する Action。
 *
 * 通常は ResolveDefaultEnrollment Middleware が 2 階層目へ redirect するため本 Action は呼ばれず、
 * default NULL かつ Enrollment 2+ 件 / 0 件 のフォールバック分岐でのみ Blade に渡るデータを返す。
 *
 * @return array{
 *     enrollments: Collection<int, Enrollment>,
 *     hasActiveEnrollments: bool,
 * }
 */
final class IndexAction
{
    /**
     * @return array<string, mixed>
     */
    public function __invoke(User $student): array
    {
        $enrollments = Enrollment::query()
            ->forUser($student)
            ->whereIn('status', [
                EnrollmentStatus::Learning->value,
                EnrollmentStatus::Passed->value,
            ])
            ->with(['certification.category'])
            ->orderBy('current_term')
            ->get();

        return [
            'enrollments' => $enrollments,
            'hasActiveEnrollments' => $enrollments->isNotEmpty(),
        ];
    }
}

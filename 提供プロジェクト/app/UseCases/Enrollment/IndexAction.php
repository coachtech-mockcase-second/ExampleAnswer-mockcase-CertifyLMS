<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * 受講生の自身の受講登録一覧取得 Action。
 *
 * ソート: current_term ASC(基礎ターム → 実践ターム) / exam_date ASC NULLS LAST。
 * eager load: certification.category / certification.coaches / latestStatusLog / 修了証(certificate) を同梱する。
 */
final class IndexAction
{
    /**
     * @return Collection<int, Enrollment>
     */
    public function __invoke(User $student): Collection
    {
        return Enrollment::query()
            ->forUser($student)
            ->with([
                'certification.category',
                'certification.coaches',
                'latestStatusLog',
                'certificate',
            ])
            // NULLS LAST: exam_date 未設定の Enrollment は最下段に集める
            ->orderByRaw('CASE WHEN exam_date IS NULL THEN 1 ELSE 0 END')
            ->orderBy('current_term')
            ->orderBy('exam_date')
            ->get();
    }
}

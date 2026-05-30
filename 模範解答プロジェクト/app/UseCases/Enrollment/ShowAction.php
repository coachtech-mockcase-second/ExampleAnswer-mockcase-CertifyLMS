<?php

declare(strict_types=1);

namespace App\UseCases\Enrollment;

use App\Models\Enrollment;

/**
 * Enrollment 詳細取得 Action。受講生 / コーチ / admin 共通(認可は Controller の Policy で済ませる前提)。
 *
 * 詳細ビューで必要な goals / coaches / 修了証 / 最新の状態遷移ログを eager load する。
 */
final class ShowAction
{
    public function __invoke(Enrollment $enrollment): Enrollment
    {
        return $enrollment->loadMissing([
            'certification.category',
            'certification.coaches',
            'certificate',
            'latestStatusLog.changedBy',
            'goals' => fn ($q) => $q->displayOrder(),
        ]);
    }
}

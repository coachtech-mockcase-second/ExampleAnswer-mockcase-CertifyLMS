<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標を達成済にマークする Action。achieved_at = now() を UPDATE。
 * 既に達成済の場合は時刻のみ書き換える(冪等)。受講生本人のみ実行可。
 */
final class MarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        $goal->update(['achieved_at' => now()]);

        return $goal->refresh();
    }
}

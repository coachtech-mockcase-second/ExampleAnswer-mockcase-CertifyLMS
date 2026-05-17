<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標の達成マークを取り消す Action。achieved_at を NULL に戻す。受講生本人のみ実行可。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): EnrollmentGoal
    {
        $goal->update(['achieved_at' => null]);

        return $goal->refresh();
    }
}

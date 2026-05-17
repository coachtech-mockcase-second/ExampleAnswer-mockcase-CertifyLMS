<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標の SoftDelete Action。受講生本人のみ実行可。
 */
final class DestroyAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        $goal->delete();
    }
}

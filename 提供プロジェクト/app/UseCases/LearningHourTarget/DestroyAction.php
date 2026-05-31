<?php

declare(strict_types=1);

namespace App\UseCases\LearningHourTarget;

use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use Illuminate\Support\Facades\DB;

/**
 * 学習時間目標の冪等 SoftDelete を行う Action。
 * 既に削除済 or 未存在の場合は副作用なしで返す。
 */
final class DestroyAction
{
    public function __invoke(Enrollment $enrollment): void
    {
        DB::transaction(function () use ($enrollment) {
            $target = LearningHourTarget::query()
                ->where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->first();

            $target?->delete();
        });
    }
}

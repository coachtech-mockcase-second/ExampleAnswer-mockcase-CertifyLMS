<?php

declare(strict_types=1);

namespace App\UseCases\LearningHourTarget;

use App\Exceptions\Learning\LearningHourTargetInvalidException;
use App\Models\Enrollment;
use App\Models\LearningHourTarget;
use Illuminate\Support\Facades\DB;

/**
 * 学習時間目標の UPSERT を行う Action。
 * SoftDelete 済の既存行があれば restore + UPDATE、無ければ新規 INSERT (冪等)。
 *
 * FormRequest が一次バリデーション (1..9999h) を担うが、内部呼出経路の保険として二重ガード。
 *
 * @throws LearningHourTargetInvalidException target_total_hours が 1..9999 の範囲外
 */
final class UpsertAction
{
    /**
     * @param array{target_total_hours: int} $validated
     */
    public function __invoke(Enrollment $enrollment, array $validated): LearningHourTarget
    {
        $hours = (int) $validated['target_total_hours'];

        if ($hours < 1 || $hours > 9999) {
            throw new LearningHourTargetInvalidException;
        }

        return DB::transaction(function () use ($enrollment, $hours) {
            $target = LearningHourTarget::query()
                ->withTrashed()
                ->where('enrollment_id', $enrollment->id)
                ->lockForUpdate()
                ->first();

            if ($target === null) {
                return LearningHourTarget::create([
                    'enrollment_id' => $enrollment->id,
                    'target_total_hours' => $hours,
                ]);
            }

            if ($target->trashed()) {
                $target->restore();
            }

            $target->update(['target_total_hours' => $hours]);

            return $target->refresh();
        });
    }
}

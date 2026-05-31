<?php

declare(strict_types=1);

namespace App\UseCases\Availability;

use App\Models\CoachAvailability;
use Illuminate\Support\Facades\DB;

/**
 * コーチ本人の面談可能時間枠を更新するユースケース。
 *
 * 本人所有確認(`CoachAvailabilityPolicy::update`)は Controller / FormRequest で完了済の前提。
 * coach_id の変更は受け付けない(他人の枠に付け替えるリスクを排除)。
 */
final class UpdateAction
{
    /**
     * @param array{day_of_week: int, start_time: string, end_time: string, is_active?: bool} $validated
     */
    public function __invoke(CoachAvailability $availability, array $validated): CoachAvailability
    {
        return DB::transaction(function () use ($availability, $validated) {
            $availability->update([
                'day_of_week' => $validated['day_of_week'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'is_active' => $validated['is_active'] ?? false,
            ]);

            return $availability->fresh();
        });
    }
}

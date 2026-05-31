<?php

declare(strict_types=1);

namespace App\UseCases\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * コーチ本人の面談可能時間枠(`CoachAvailability`)一覧を取得するユースケース。
 *
 * 編集 UI で使う前提のため SoftDelete 済の行は含めず、曜日 → 開始時刻の順で並べる。
 * 同一コーチ × 同曜日に時刻範囲が重複する複数枠(例: 午前 / 午後)も許容して取得する。
 */
final class IndexAction
{
    /**
     * @return Collection<int, CoachAvailability>
     */
    public function __invoke(User $coach): Collection
    {
        return CoachAvailability::query()
            ->where('coach_id', $coach->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }
}

<?php

declare(strict_types=1);

namespace App\UseCases\Availability;

use App\Models\CoachAvailability;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * コーチ本人の面談可能時間枠を新規作成するユースケース。
 *
 * coach_id は常に認証ユーザー本人で固定し、フォームから受け取らない(他人の枠を作るリスクを排除)。
 * 同一コーチ × 同曜日に時刻範囲が重複する複数枠の登録も許容する(午前 / 午後など分割管理に有用)。
 */
final class StoreAction
{
    /**
     * @param array{day_of_week: int, start_time: string, end_time: string, is_active?: bool} $validated
     */
    public function __invoke(User $coach, array $validated): CoachAvailability
    {
        return DB::transaction(fn () => CoachAvailability::create([
            'coach_id' => $coach->id,
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'is_active' => $validated['is_active'] ?? true,
        ]));
    }
}
